<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Resolves application users for OIDC SSO and password login using federated identity
 * and org email domain equivalence from application/config/auth.php (global).
 */
class Oidc_user_resolver {

	const ERROR_AMBIGUOUS_LOCAL_PART = 'ambiguous_local_part';
	const ERROR_IDENTITY_CONFLICT = 'identity_conflict';
	const ERROR_USER_NOT_FOUND = 'user_not_found';

	/** @var CI_Controller */
	protected $ci;

	/** @var array */
	protected $oidc_config;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Ion_auth_model');
		$this->oidc_config = $this->ci->config->item('oidc_auth');
		if (!is_array($this->oidc_config)) {
			$this->oidc_config = array();
		}
	}

	/**
	 * @param array $claims Decoded ID token claims
	 * @param string $token_email Email from mapped user profile
	 * @return array
	 */
	public function resolve_for_oidc($claims, $token_email)
	{
		$identity = $this->extract_identity_from_claims($claims);
		$token_email = $this->normalize_email($token_email);

		if (!empty($identity['subject']) && !empty($identity['issuer'])) {
			$user = $this->ci->Ion_auth_model->get_user_by_federated_identity(
				$identity['issuer'],
				$identity['namespace'],
				$identity['subject']
			);
			if ($user) {
				return $this->success_result($user, 'identity', $identity);
			}
		}

		if ($token_email !== '') {
			$by_email = $this->resolve_by_email_rules($token_email, $identity, true);
			if ($by_email['status'] === 'error') {
				return $by_email;
			}
			if ($by_email['status'] === 'found') {
				return $by_email;
			}
		}

		return array(
			'status' => 'register',
			'user' => null,
			'matched_by' => null,
			'identity' => $identity,
			'error' => null,
		);
	}

	/**
	 * Password login: resolve typed email to a user row (no IdP claims).
	 *
	 * @param string $email
	 * @return array
	 */
	public function resolve_for_password_login($email)
	{
		$email = $this->normalize_email($email);
		if ($email === '') {
			return $this->error_result(self::ERROR_USER_NOT_FOUND, null);
		}

		$result = $this->resolve_by_email_rules($email, null, false);
		if ($result['status'] === 'not_found') {
			return $this->error_result(self::ERROR_USER_NOT_FOUND, null);
		}
		return $result;
	}

	/**
	 * Forgot-password flow: resolve posted email to an account (same rules as password login).
	 *
	 * @param string $email
	 * @return array same shape as resolve_for_password_login
	 */
	public function resolve_for_forgot_password($email)
	{
		$email = $this->normalize_email($email);
		if ($email === '') {
			return $this->error_result(self::ERROR_USER_NOT_FOUND, null);
		}

		$result = $this->resolve_by_email_rules($email, null, false);
		if ($result['status'] === 'not_found') {
			return $this->error_result(self::ERROR_USER_NOT_FOUND, null);
		}
		return $result;
	}

	const REGISTRATION_EMAIL_AVAILABLE = 'available';
	const REGISTRATION_EMAIL_TAKEN = 'taken';
	const REGISTRATION_EMAIL_AMBIGUOUS = 'ambiguous';

	/**
	 * Whether an email may be used for self-registration (exact + domain equivalence).
	 *
	 * @param string $email
	 * @return string REGISTRATION_EMAIL_* constant
	 */
	public function check_registration_email($email)
	{
		$email = $this->normalize_email($email);
		if ($email === '') {
			return self::REGISTRATION_EMAIL_AVAILABLE;
		}

		if ($this->ci->Ion_auth_model->get_user_by_email_normalized($email)) {
			return self::REGISTRATION_EMAIL_TAKEN;
		}

		$equiv = $this->get_domain_equivalence_config();
		if (empty($equiv['enabled']) || empty($equiv['local_part_cross_domain'])) {
			return self::REGISTRATION_EMAIL_AVAILABLE;
		}

		$parts = $this->parse_email_parts($email);
		if (!$parts || !in_array($parts['domain'], $equiv['domains'], true)) {
			return self::REGISTRATION_EMAIL_AVAILABLE;
		}

		$candidates = $this->ci->Ion_auth_model->find_users_by_local_part_in_domains(
			$parts['local_part'],
			$equiv['domains']
		);

		if (count($candidates) === 0) {
			return self::REGISTRATION_EMAIL_AVAILABLE;
		}

		if (count($candidates) > 1 && !empty($equiv['require_unique_local_part'])) {
			return self::REGISTRATION_EMAIL_AMBIGUOUS;
		}

		return self::REGISTRATION_EMAIL_TAKEN;
	}

	/**
	 * @param string $email
	 * @return bool true if an account already exists for this registration email
	 */
	public function registration_email_is_taken($email)
	{
		$status = $this->check_registration_email($email);
		return $status !== self::REGISTRATION_EMAIL_AVAILABLE;
	}

	/**
	 * Persist federated identity and canonical email after SSO link.
	 *
	 * @param int $user_id
	 * @param array $identity from extract_identity_from_claims
	 * @param string $token_email
	 * @return bool
	 * @throws Exception
	 */
	public function link_oidc_identity($user_id, $identity, $token_email)
	{
		if (empty($identity['subject']) || empty($identity['issuer'])) {
			return true;
		}

		$existing = $this->ci->Ion_auth_model->get_user_by_federated_identity(
			$identity['issuer'],
			$identity['namespace'],
			$identity['subject']
		);
		if ($existing && (int) $existing->id !== (int) $user_id) {
			throw new Exception('OIDC identity is already linked to another account');
		}

		$user = $this->ci->Ion_auth_model->get_user_by_id($user_id);
		if (!$user) {
			throw new Exception('User not found');
		}

		if (!$this->user_identity_is_empty($user) && !$this->identity_matches_user($user, $identity)) {
			throw new Exception('Account already linked to a different OIDC identity');
		}

		$token_email = $this->normalize_email($token_email);
		if ($token_email !== '' && $this->email_owned_by_other_user($token_email, $user_id)) {
			throw new Exception('Email address is already used by another account');
		}

		return $this->ci->Ion_auth_model->set_user_federated_identity(
			$user_id,
			$identity['issuer'],
			$identity['namespace'],
			$identity['subject'],
			$identity['subject_claim'],
			$token_email !== '' ? $token_email : null
		);
	}

	/**
	 * @param array $claims
	 * @return array issuer, namespace, subject, subject_claim
	 */
	public function extract_identity_from_claims($claims)
	{
		$claims = json_decode(json_encode($claims), true);
		if (!is_array($claims)) {
			$claims = array();
		}

		$user_identity = isset($this->oidc_config['user_identity']) ? $this->oidc_config['user_identity'] : array();
		$subject_claim = isset($user_identity['subject_claim']) ? $user_identity['subject_claim'] : 'sub';
		$namespace_claim = isset($user_identity['namespace_claim']) ? $user_identity['namespace_claim'] : null;

		$issuer = isset($claims['iss']) ? rtrim((string) $claims['iss'], '/') : '';
		$subject = isset($claims[$subject_claim]) ? (string) $claims[$subject_claim] : '';
		$namespace = '';
		if ($namespace_claim && isset($claims[$namespace_claim])) {
			$namespace = (string) $claims[$namespace_claim];
		}

		return array(
			'issuer' => $issuer,
			'namespace' => $namespace,
			'subject' => $subject,
			'subject_claim' => $subject_claim,
		);
	}

	public function normalize_email($email)
	{
		return strtolower(trim((string) $email));
	}

	protected function success_result($user, $matched_by, $identity)
	{
		return array(
			'status' => 'found',
			'user' => $user,
			'matched_by' => $matched_by,
			'identity' => $identity,
			'error' => null,
		);
	}

	protected function error_result($code, $identity)
	{
		return array(
			'status' => 'error',
			'user' => null,
			'matched_by' => null,
			'identity' => $identity,
			'error' => $code,
		);
	}

	protected function resolve_by_email_rules($email, $identity, $oidc_context)
	{
		$user = $this->ci->Ion_auth_model->get_user_by_email_normalized($email);
		if ($user) {
			if ($oidc_context && $identity && !empty($identity['subject'])) {
				if ($this->check_identity_email_conflict($user, $identity)) {
					return $this->error_result(self::ERROR_IDENTITY_CONFLICT, $identity);
				}
			}
			return $this->success_result($user, 'email', $identity);
		}

		$equiv = $this->get_domain_equivalence_config();
		if (empty($equiv['enabled']) || empty($equiv['local_part_cross_domain'])) {
			return array(
				'status' => 'not_found',
				'user' => null,
				'matched_by' => null,
				'identity' => $identity,
				'error' => null,
			);
		}

		$parts = $this->parse_email_parts($email);
		if (!$parts || !in_array($parts['domain'], $equiv['domains'], true)) {
			return array(
				'status' => 'not_found',
				'user' => null,
				'matched_by' => null,
				'identity' => $identity,
				'error' => null,
			);
		}

		$candidates = $this->ci->Ion_auth_model->find_users_by_local_part_in_domains(
			$parts['local_part'],
			$equiv['domains']
		);

		if (count($candidates) === 0) {
			return array(
				'status' => 'not_found',
				'user' => null,
				'matched_by' => null,
				'identity' => $identity,
				'error' => null,
			);
		}

		if (count($candidates) > 1 && !empty($equiv['require_unique_local_part'])) {
			log_message('error', 'OIDC/email resolve: ambiguous local_part "' . $parts['local_part'] . '"');
			return $this->error_result(self::ERROR_AMBIGUOUS_LOCAL_PART, $identity);
		}

		$user = $candidates[0];
		if ($oidc_context && $identity && !empty($identity['subject'])) {
			if ($this->check_identity_email_conflict($user, $identity)) {
				return $this->error_result(self::ERROR_IDENTITY_CONFLICT, $identity);
			}
		}

		return $this->success_result($user, 'local_part', $identity);
	}

	protected function get_domain_equivalence_config()
	{
		$cfg = $this->ci->config->item('email_domain_equivalence');
		if (!is_array($cfg)) {
			$cfg = isset($this->oidc_config['email_domain_equivalence']) && is_array($this->oidc_config['email_domain_equivalence'])
				? $this->oidc_config['email_domain_equivalence']
				: array();
		}
		$domains = isset($cfg['domains']) && is_array($cfg['domains']) ? $cfg['domains'] : array();
		$normalized_domains = array();
		foreach ($domains as $d) {
			$d = strtolower(trim((string) $d));
			if ($d !== '') {
				$normalized_domains[] = $d;
			}
		}

		return array(
			'enabled' => !empty($cfg['enabled']),
			'domains' => array_values(array_unique($normalized_domains)),
			'local_part_cross_domain' => !isset($cfg['local_part_cross_domain']) || $cfg['local_part_cross_domain'],
			'require_unique_local_part' => !isset($cfg['require_unique_local_part']) || $cfg['require_unique_local_part'],
		);
	}

	protected function parse_email_parts($email)
	{
		$email = $this->normalize_email($email);
		$pos = strrpos($email, '@');
		if ($pos === false || $pos === 0 || $pos === strlen($email) - 1) {
			return null;
		}
		return array(
			'local_part' => substr($email, 0, $pos),
			'domain' => substr($email, $pos + 1),
		);
	}

	protected function user_identity_is_empty($user)
	{
		return empty($user->identity_subject) || empty($user->identity_issuer);
	}

	protected function identity_matches_user($user, $identity)
	{
		return rtrim((string) $user->identity_issuer, '/') === $identity['issuer']
			&& (string) $user->identity_namespace === (string) $identity['namespace']
			&& (string) $user->identity_subject === (string) $identity['subject'];
	}

	protected function check_identity_email_conflict($user, $identity)
	{
		if ($this->user_identity_is_empty($user)) {
			return false;
		}
		return !$this->identity_matches_user($user, $identity);
	}

	protected function email_owned_by_other_user($email, $user_id)
	{
		$other = $this->ci->Ion_auth_model->get_user_by_email_normalized($email);
		return $other && (int) $other->id !== (int) $user_id;
	}

	public function error_message_for_code($error_code)
	{
		if (function_exists('t')) {
			if ($error_code === self::ERROR_AMBIGUOUS_LOCAL_PART) {
				$msg = t('login_ambiguous_email');
				if ($msg !== 'login_ambiguous_email') {
					return $msg;
				}
			}
			if ($error_code === self::ERROR_IDENTITY_CONFLICT) {
				$msg = t('login_identity_conflict');
				if ($msg !== 'login_identity_conflict') {
					return $msg;
				}
			}
		}
		if ($error_code === self::ERROR_AMBIGUOUS_LOCAL_PART) {
			return 'Multiple accounts match this sign-in. Contact an administrator to merge duplicate accounts.';
		}
		if ($error_code === self::ERROR_IDENTITY_CONFLICT) {
			return 'This sign-in could not be linked to your account. Contact an administrator.';
		}
		return 'Sign-in failed.';
	}

	/**
	 * Password login using global email domain equivalence (all auth drivers).
	 *
	 * @param string $posted_email
	 * @param string $password
	 * @param bool $remember
	 * @return bool
	 */
	public function attempt_password_login($posted_email, $password, $remember = false)
	{
		$this->ci->load->library('ion_auth');
		$resolved = $this->resolve_for_password_login($posted_email);

		if ($resolved['status'] === 'error') {
			if ($resolved['error'] === self::ERROR_AMBIGUOUS_LOCAL_PART) {
				$this->ci->session->set_flashdata(
					'error',
					$this->error_message_for_code($resolved['error'])
				);
				return false;
			}
			return $this->ci->ion_auth->login($posted_email, $password, $remember);
		}

		if ($resolved['status'] === 'found' && !empty($resolved['user']->email)) {
			return $this->ci->ion_auth->login($resolved['user']->email, $password, $remember);
		}

		return $this->ci->ion_auth->login($posted_email, $password, $remember);
	}

	/**
	 * Send forgot-password message using global email resolution.
	 *
	 * @param string $posted_email
	 * @return bool|null true on success, false if no account, null if ambiguous
	 */
	public function attempt_forgot_password($posted_email)
	{
		$this->ci->load->library('ion_auth');
		$resolved = $this->resolve_for_forgot_password($posted_email);

		if ($resolved['status'] === 'error') {
			if ($resolved['error'] === self::ERROR_AMBIGUOUS_LOCAL_PART) {
				$this->ci->session->set_flashdata(
					'message',
					$this->error_message_for_code($resolved['error'])
				);
				return null;
			}
			return false;
		}

		if ($resolved['status'] === 'found' && !empty($resolved['user']->email)) {
			return $this->ci->ion_auth->forgotten_password($resolved['user']->email);
		}

		return false;
	}
}
