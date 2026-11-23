Continue the Eco Metrics project.

Read CLAUDE.md and inspect the existing authentication and database structure.

TASKS:

1. Create the login page.
2. Implement logout functionality.
3. Implement secure PHP sessions.
4. Implement password_hash() and password_verify().
5. Create authentication middleware.
6. Implement role-based access control.
7. Add CSRF protection.
8. Validate all login inputs.
9. Add login error messages using SweetAlert2.
10. Protect all private routes.

USER ROLES:

System Administrator:
- Full system access.
- Manage all organizational records.
- Manage appliances.
- Manage users.
- Manage electricity rates.
- Manage emission factors.
- Access all reports and analytics.

Department/Office User:
- Access only their assigned office.
- View their office dashboard.
- Manage authorized appliances.
- View office consumption.
- Generate authorized reports.

Make sure permissions are enforced in PHP, not just through the interface.

TESTING:
Test successful login, invalid credentials, logout, session expiration, and unauthorized access.

Do not proceed to dashboard development. Wait for approval.