-- Federated identity columns for OIDC / external IdP account linking
ALTER TABLE `users`
  ADD COLUMN `identity_issuer` varchar(255) DEFAULT NULL,
  ADD COLUMN `identity_namespace` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN `identity_subject` varchar(255) DEFAULT NULL,
  ADD COLUMN `identity_subject_claim` varchar(64) DEFAULT NULL;

ALTER TABLE `users`
  ADD UNIQUE KEY `uq_users_federated_identity` (`identity_issuer`, `identity_namespace`, `identity_subject`);
