Continue the existing Eco Metrics project.

Before coding, read CLAUDE.md and inspect the existing project structure.

TASKS:

1. Create the MySQL database named eco_metrics_db.
2. Design a normalized relational database.
3. Create schema.sql.
4. Create seeders.sql with realistic demonstration data.
5. Create the PDO database connection.
6. Configure database credentials using environment variables.
7. Create a reusable database connection class.
8. Implement proper exception handling.
9. Use prepared statements for database operations.

Create these tables:

- roles
- users
- user_permissions
- campuses
- buildings
- departments
- offices
- appliance_categories
- appliances
- appliance_usage_records
- electricity_rates
- emission_factors
- consumption_records
- consumption_thresholds
- notifications
- audit_logs
- system_settings

Ensure correct foreign key relationships, indexes, timestamps, and appropriate data types.

IMPORTANT:
The database must be compatible with Laragon MySQL.

TESTING:
- Test database connection.
- Test table creation.
- Test foreign key relationships.
- Test sample data insertion.

Do not proceed to authentication yet. Stop and wait for approval.