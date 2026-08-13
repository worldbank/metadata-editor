<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Editor-native variable group tree.
 *
 * Storage shape is a nested list of groups. Membership is editor_variables.uid.
 * DDI flatten / VID mapping is an export concern, not storage.
 */
class Variable_groups_tree
{
	const STRING_FIELDS = array(
		'vgid',
		'group_type',
		'label',
		'txt',
		'universe',
		'notes',
		'definition',
	);

	/**
	 * Strict normalize for writes.
	 *
	 * @param mixed $metadata
	 * @return array
	 * @throws Exception
	 */
	public static function normalize($metadata)
	{
		if ($metadata === null || $metadata === '') {
			return array();
		}

		if (!is_array($metadata)) {
			throw new Exception('variable_groups must be an array');
		}

		if (!self::is_list($metadata)) {
			throw new Exception('variable_groups must be a list of groups');
		}

		$seen_vgids = array();
		return self::normalize_list($metadata, $seen_vgids);
	}

	/**
	 * Best-effort normalize for reads of legacy rows. Never throws.
	 *
	 * @param mixed $metadata
	 * @return array
	 */
	public static function coerce($metadata)
	{
		try {
			return self::normalize($metadata);
		} catch (Exception $e) {
			return self::coerce_relaxed($metadata);
		}
	}

	/**
	 * Drop UIDs from every group. Used when variables are deleted.
	 *
	 * @param array $tree
	 * @param array $uid_list
	 * @return array
	 */
	public static function remove_uids($tree, $uid_list)
	{
		$remove = array();
		foreach ((array) $uid_list as $uid) {
			$uid = (int) $uid;
			if ($uid > 0) {
				$remove[$uid] = true;
			}
		}

		return self::map_groups($tree, function ($group) use ($remove) {
			$kept = array();
			$seen = array();
			foreach (self::variables_of($group) as $uid) {
				if ($uid > 0 && !isset($remove[$uid]) && !isset($seen[$uid])) {
					$kept[] = $uid;
					$seen[$uid] = true;
				}
			}
			$group['variables'] = $kept;
			return $group;
		});
	}

	/**
	 * Flatten a nested UID tree to interchange rows (space-separated VID/VGID).
	 * Matches variable-group-schema.json. Unmapped UIDs are dropped.
	 *
	 * @param array $tree
	 * @param array $uid_to_vid uid => vid
	 * @return array
	 */
	public static function flatten_for_export($tree, $uid_to_vid)
	{
		$flat = array();
		self::flatten_walk($tree, (array) $uid_to_vid, $flat);
		return $flat;
	}

	/**
	 * Rebuild a nested UID tree from import payload.
	 *
	 * Accepts DDI/JSON interchange (flat IDREFS) or an already-nested tree.
	 * VID tokens are mapped to UIDs; unknown VIDs are dropped.
	 *
	 * @param mixed $groups
	 * @param array $vid_to_uid vid => uid
	 * @return array
	 */
	public static function nest_from_import($groups, $vid_to_uid)
	{
		if ($groups === null || $groups === '' || $groups === array()) {
			return array();
		}

		if (!is_array($groups)) {
			return array();
		}

		if (!self::is_list($groups)) {
			$groups = array($groups);
		}

		if (self::is_flat_interchange($groups)) {
			$groups = self::rebuild_tree_from_flat($groups);
		}

		$mapped = array();
		foreach ($groups as $group) {
			if (is_array($group) && !self::is_list($group)) {
				$mapped[] = self::map_import_group($group, (array) $vid_to_uid);
			}
		}
		return $mapped;
	}

	/**
	 * Schema/UI type -> DDI varGrp/@type (multiResp -> multipleResp).
	 *
	 * @param mixed $type
	 * @return string
	 */
	public static function to_ddi_group_type($type)
	{
		$type = (string) $type;
		if ($type === 'multiResp') {
			return 'multipleResp';
		}
		return $type;
	}

	/**
	 * DDI varGrp/@type -> schema/UI type (multipleResp -> multiResp).
	 *
	 * @param mixed $type
	 * @return string
	 */
	public static function from_ddi_group_type($type)
	{
		$type = (string) $type;
		if ($type === 'multipleResp') {
			return 'multiResp';
		}
		return $type;
	}

	/**
	 * Rewrite membership UIDs after a project/version copy.
	 * Unmapped UIDs are dropped.
	 *
	 * @param array $tree
	 * @param array $uid_map old_uid => new_uid
	 * @return array
	 */
	public static function remap_uids($tree, $uid_map)
	{
		$map = array();
		foreach ((array) $uid_map as $old_uid => $new_uid) {
			$old_uid = (int) $old_uid;
			$new_uid = (int) $new_uid;
			if ($old_uid > 0 && $new_uid > 0) {
				$map[$old_uid] = $new_uid;
			}
		}

		return self::map_groups($tree, function ($group) use ($map) {
			$kept = array();
			$seen = array();
			foreach (self::variables_of($group) as $uid) {
				if (!isset($map[$uid])) {
					continue;
				}
				$mapped = $map[$uid];
				if (!isset($seen[$mapped])) {
					$kept[] = $mapped;
					$seen[$mapped] = true;
				}
			}
			$group['variables'] = $kept;
			return $group;
		});
	}

	private static function normalize_list($items, &$seen_vgids)
	{
		$out = array();
		foreach ($items as $item) {
			if (!is_array($item) || self::is_list($item)) {
				throw new Exception('Each variable group must be an object');
			}
			$out[] = self::normalize_group($item, $seen_vgids);
		}
		return $out;
	}

	private static function normalize_group($group, &$seen_vgids)
	{
		$vgid = isset($group['vgid']) ? trim((string) $group['vgid']) : '';
		if ($vgid === '') {
			throw new Exception('Each variable group requires a vgid');
		}
		if (isset($seen_vgids[$vgid])) {
			throw new Exception('Duplicate variable group id: ' . $vgid);
		}
		$seen_vgids[$vgid] = true;

		$normalized = $group;
		$normalized['vgid'] = $vgid;

		foreach (self::STRING_FIELDS as $field) {
			if ($field === 'vgid') {
				continue;
			}
			if (array_key_exists($field, $group) && $group[$field] !== null && is_scalar($group[$field])) {
				$normalized[$field] = (string) $group[$field];
			}
		}

		if (isset($group['concepts']) && is_array($group['concepts'])) {
			$normalized['concepts'] = self::normalize_concepts($group['concepts']);
		}

		$normalized['variables'] = self::normalize_variables(isset($group['variables']) ? $group['variables'] : array());
		$normalized['variable_groups'] = self::normalize_children(isset($group['variable_groups']) ? $group['variable_groups'] : array(), $seen_vgids);

		return $normalized;
	}

	private static function normalize_variables($variables)
	{
		if ($variables === null || $variables === '') {
			return array();
		}

		if (is_string($variables)) {
			$trimmed = trim($variables);
			if ($trimmed === '') {
				return array();
			}
			if (preg_match('/^\d+$/', $trimmed)) {
				return array((int) $trimmed);
			}
			throw new Exception('variables must be an array of variable UIDs');
		}

		if (!is_array($variables) || !self::is_list($variables)) {
			throw new Exception('variables must be an array of variable UIDs');
		}

		$out = array();
		$seen = array();
		foreach ($variables as $uid) {
			if (is_bool($uid) || (is_string($uid) && !preg_match('/^\d+$/', trim($uid)))) {
				throw new Exception('variables must be an array of variable UIDs');
			}
			if (!is_int($uid) && !is_float($uid) && !is_string($uid)) {
				throw new Exception('variables must be an array of variable UIDs');
			}
			$uid = (int) $uid;
			if ($uid <= 0) {
				throw new Exception('variables must be an array of variable UIDs');
			}
			if (!isset($seen[$uid])) {
				$out[] = $uid;
				$seen[$uid] = true;
			}
		}

		return $out;
	}

	private static function normalize_children($children, &$seen_vgids)
	{
		if ($children === null || $children === '') {
			return array();
		}

		if (is_string($children)) {
			throw new Exception('variable_groups must be nested group objects, not ID strings');
		}

		if (!is_array($children) || !self::is_list($children)) {
			throw new Exception('variable_groups must be an array of groups');
		}

		return self::normalize_list($children, $seen_vgids);
	}

	private static function normalize_concepts($concepts)
	{
		$out = array();
		foreach ($concepts as $row) {
			if (!is_array($row)) {
				continue;
			}
			$out[] = array(
				'concept' => isset($row['concept']) ? (string) $row['concept'] : '',
				'vocab' => isset($row['vocab']) ? (string) $row['vocab'] : '',
				'uri' => isset($row['uri']) ? (string) $row['uri'] : '',
			);
		}
		return $out;
	}

	private static function coerce_relaxed($metadata)
	{
		if (!is_array($metadata)) {
			return array();
		}

		if (!self::is_list($metadata)) {
			$metadata = array($metadata);
		}

		$out = array();
		foreach ($metadata as $item) {
			if (!is_array($item) || self::is_list($item)) {
				continue;
			}
			$out[] = self::coerce_group($item);
		}
		return $out;
	}

	private static function coerce_group($group)
	{
		$group['vgid'] = isset($group['vgid']) ? trim((string) $group['vgid']) : '';
		$group['variables'] = self::coerce_variables(isset($group['variables']) ? $group['variables'] : array());

		$children = isset($group['variable_groups']) ? $group['variable_groups'] : array();
		if (!is_array($children) || !self::is_list($children)) {
			$children = array();
		}
		$group['variable_groups'] = self::coerce_relaxed($children);

		return $group;
	}

	private static function coerce_variables($variables)
	{
		if (is_string($variables)) {
			$variables = preg_split('/\s+/', trim($variables), -1, PREG_SPLIT_NO_EMPTY);
		}
		if (!is_array($variables)) {
			return array();
		}

		$out = array();
		$seen = array();
		foreach ($variables as $uid) {
			if (!is_int($uid) && !is_float($uid) && !(is_string($uid) && preg_match('/^\d+$/', trim($uid)))) {
				continue;
			}
			$uid = (int) $uid;
			if ($uid > 0 && !isset($seen[$uid])) {
				$out[] = $uid;
				$seen[$uid] = true;
			}
		}
		return $out;
	}

	private static function map_groups($tree, $callback)
	{
		if (!is_array($tree) || !self::is_list($tree)) {
			return array();
		}

		$out = array();
		foreach ($tree as $group) {
			if (!is_array($group) || self::is_list($group)) {
				continue;
			}
			$group = $callback($group);
			$children = isset($group['variable_groups']) ? $group['variable_groups'] : array();
			$group['variable_groups'] = self::map_groups($children, $callback);
			$out[] = $group;
		}
		return $out;
	}

	private static function variables_of($group)
	{
		$variables = isset($group['variables']) ? $group['variables'] : array();
		if (!is_array($variables)) {
			return array();
		}

		$out = array();
		foreach ($variables as $uid) {
			$out[] = (int) $uid;
		}
		return $out;
	}

	private static function flatten_walk($tree, $uid_to_vid, &$flat)
	{
		if (!is_array($tree) || !self::is_list($tree)) {
			return;
		}

		foreach ($tree as $group) {
			if (!is_array($group) || self::is_list($group)) {
				continue;
			}

			$children = isset($group['variable_groups']) ? $group['variable_groups'] : array();
			$child_ids = array();
			if (is_array($children) && self::is_list($children)) {
				foreach ($children as $child) {
					if (!is_array($child) || self::is_list($child)) {
						continue;
					}
					$child_vgid = isset($child['vgid']) ? trim((string) $child['vgid']) : '';
					if ($child_vgid !== '') {
						$child_ids[] = $child_vgid;
					}
				}
			}

			$vgid = isset($group['vgid']) ? trim((string) $group['vgid']) : '';
			if ($vgid === '') {
				self::flatten_walk($children, $uid_to_vid, $flat);
				continue;
			}

			$vids = array();
			$seen_vids = array();
			foreach (self::variables_of($group) as $uid) {
				if (!isset($uid_to_vid[$uid]) || (string) $uid_to_vid[$uid] === '') {
					continue;
				}
				$vid = $uid_to_vid[$uid];
				if (isset($seen_vids[$vid])) {
					continue;
				}
				$vids[] = $vid;
				$seen_vids[$vid] = true;
			}

			$row = array('vgid' => $vgid);
			$type = isset($group['group_type']) ? (string) $group['group_type'] : '';
			if ($type !== '') {
				$row['group_type'] = $type;
			}

			foreach (array('label', 'txt', 'universe', 'notes', 'definition') as $field) {
				if (isset($group[$field]) && is_scalar($group[$field]) && (string) $group[$field] !== '') {
					$row[$field] = (string) $group[$field];
				}
			}

			if (isset($group['concepts']) && is_array($group['concepts']) && $group['concepts'] !== array()) {
				$row['concepts'] = $group['concepts'];
			}

			if ($vids !== array()) {
				$row['variables'] = implode(' ', $vids);
			}
			if ($child_ids !== array()) {
				$row['variable_groups'] = implode(' ', $child_ids);
			}

			$flat[] = $row;
			self::flatten_walk($children, $uid_to_vid, $flat);
		}
	}

	private static function is_flat_interchange($groups)
	{
		foreach ($groups as $group) {
			if (!is_array($group) || self::is_list($group)) {
				continue;
			}

			if (isset($group['variable_groups']) && is_string($group['variable_groups'])) {
				return true;
			}

			if (isset($group['variables']) && is_string($group['variables'])) {
				$trimmed = trim($group['variables']);
				if ($trimmed !== '' && !preg_match('/^\d+$/', $trimmed)) {
					return true;
				}
			}

			if (isset($group['variable_groups']) && is_array($group['variable_groups']) && self::is_list($group['variable_groups']) && $group['variable_groups'] !== array()) {
				$first = $group['variable_groups'][0];
				if (is_string($first)) {
					return true;
				}
			}

			if (isset($group['variables']) && is_array($group['variables']) && self::is_list($group['variables']) && $group['variables'] !== array()) {
				$first = $group['variables'][0];
				if (is_string($first) && !preg_match('/^\d+$/', trim($first))) {
					return true;
				}
			}
		}

		return false;
	}

	private static function rebuild_tree_from_flat($groups)
	{
		$by_id = array();
		$order = array();
		foreach ($groups as $group) {
			if (!is_array($group) || self::is_list($group)) {
				continue;
			}
			$vgid = isset($group['vgid']) ? trim((string) $group['vgid']) : '';
			if ($vgid === '') {
				continue;
			}
			$by_id[$vgid] = $group;
			$order[] = $vgid;
		}

		$is_child = array();
		$children_of = array();
		foreach ($by_id as $vgid => $group) {
			$child_ids = self::split_ids(isset($group['variable_groups']) ? $group['variable_groups'] : array());
			$children_of[$vgid] = $child_ids;
			foreach ($child_ids as $cid) {
				if (isset($by_id[$cid])) {
					$is_child[$cid] = true;
				}
			}
		}

		$attached = array();
		$attach = function ($vgid, $stack) use (&$attach, &$by_id, &$children_of, &$attached) {
			$group = $by_id[$vgid];
			$kids = array();
			$child_ids = isset($children_of[$vgid]) ? $children_of[$vgid] : array();
			foreach ($child_ids as $cid) {
				if (!isset($by_id[$cid]) || isset($stack[$cid]) || isset($attached[$cid])) {
					continue;
				}
				$attached[$cid] = true;
				$stack[$cid] = true;
				$kids[] = $attach($cid, $stack);
			}
			$group['variable_groups'] = $kids;
			return $group;
		};

		$roots = array();
		foreach ($order as $vgid) {
			if (!isset($is_child[$vgid])) {
				$roots[] = $attach($vgid, array($vgid => true));
			}
		}
		return $roots;
	}

	private static function map_import_group($group, $vid_to_uid)
	{
		if (isset($group['group_type'])) {
			$group['group_type'] = self::from_ddi_group_type($group['group_type']);
		}

		$group['variables'] = self::membership_to_uids(
			isset($group['variables']) ? $group['variables'] : array(),
			$vid_to_uid
		);

		$children = isset($group['variable_groups']) ? $group['variable_groups'] : array();
		$mapped = array();
		if (is_array($children) && self::is_list($children)) {
			foreach ($children as $child) {
				if (is_array($child) && !self::is_list($child)) {
					$mapped[] = self::map_import_group($child, $vid_to_uid);
				}
			}
		}
		$group['variable_groups'] = $mapped;

		return $group;
	}

	private static function membership_to_uids($variables, $vid_to_uid)
	{
		$tokens = self::split_ids($variables);
		$uids = array();
		$seen = array();
		foreach ($tokens as $token) {
			$uid = null;
			if (preg_match('/^\d+$/', $token)) {
				$uid = (int) $token;
			} elseif (isset($vid_to_uid[$token])) {
				$uid = (int) $vid_to_uid[$token];
			} else {
				$upper = strtoupper($token);
				if (isset($vid_to_uid[$upper])) {
					$uid = (int) $vid_to_uid[$upper];
				}
			}
			if ($uid !== null && $uid > 0 && !isset($seen[$uid])) {
				$uids[] = $uid;
				$seen[$uid] = true;
			}
		}
		return $uids;
	}

	private static function split_ids($value)
	{
		if ($value === null || $value === '') {
			return array();
		}
		if (is_int($value) || is_float($value)) {
			return array((string) (int) $value);
		}
		if (is_string($value)) {
			return preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
		}
		if (!is_array($value) || !self::is_list($value)) {
			return array();
		}

		$out = array();
		foreach ($value as $item) {
			if (is_int($item) || is_float($item)) {
				$out[] = (string) (int) $item;
			} elseif (is_string($item) && trim($item) !== '') {
				foreach (preg_split('/\s+/', trim($item), -1, PREG_SPLIT_NO_EMPTY) as $token) {
					$out[] = $token;
				}
			}
		}
		return $out;
	}

	private static function is_list($value)
	{
		if (!is_array($value)) {
			return false;
		}
		if ($value === array()) {
			return true;
		}
		return array_keys($value) === range(0, count($value) - 1);
	}
}
