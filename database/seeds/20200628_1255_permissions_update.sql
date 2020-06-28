-- Insert new permission
INSERT IGNORE INTO permissions (name, display_name, description, created_at) VALUES ('publish-round','publish-round','Allows user to publish/finalize results for a round.', now());
-- Grant the admin role (ID=1) this new permission
INSERT IGNORE INTO permission_role (permission_id, role_id) SELECT id, 1 FROM permissions;

