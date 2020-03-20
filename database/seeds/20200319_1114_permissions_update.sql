-- Insert new permission
INSERT IGNORE INTO permissions (name, display_name, description, created_at) VALUES ('create-survey-question','Add survey question','Allows a user to add a survey question for the current PT round', now());
INSERT IGNORE INTO permissions (name, display_name, description, created_at) VALUES ('update-survey-question','Update survey question','Allows a user to update a survey question', now());
INSERT IGNORE INTO permissions (name, display_name, description, created_at) VALUES ('delete-survey-question','Delete survey question','Allows a user to delete a survey question', now());

INSERT IGNORE INTO permissions (name, display_name, description, created_at) VALUES ('create-customer-survey-response','Reply to a customer survey','Allows user to reply to an existing customer survey', now());
INSERT IGNORE INTO permissions (name, display_name, description, created_at) VALUES ('view-customer-survey-responses','View customer survey responses','View customer survey responses', now());
-- Grant the admin role (ID=1) this new permission
INSERT IGNORE INTO permission_role (permission_id, role_id) SELECT id, 1 FROM permissions;

