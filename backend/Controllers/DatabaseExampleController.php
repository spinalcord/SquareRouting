<?php

namespace SquareRouting\Controllers;

use Exception;
use SquareRouting\Core\Database;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response;

class DatabaseExampleController
{
    private Database $db;

    public function __construct(DependencyContainer $container)
    {
        $this->db = $container->get(Database::class);
    }

    public function databaseExamples(): Response
    {
        $results = [];

        // 1. Create Table Example
        try {
            $tableName = 'users';
            $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->db->query($sql);
            $results['create_table'] = ['status' => 'success', 'message' => "Table '{$tableName}' ensured to exist."];
        } catch (Exception $e) {
            $results['create_table'] = ['status' => 'error', 'message' => 'Create table failed: ' . $e->getMessage()];
        }

        // 2. Insert Example
        try {
            $initialUserCount = $this->db->count('users');

            if ($initialUserCount < 3) { // Ensure we don't insert too many times
                $insertedId1 = $this->db->insert('users', [
                    'username' => 'test_user_' . uniqid(),
                    'email' => 'test_' . uniqid() . '@example.com',
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                ]);
                $insertedId2 = $this->db->insert('users', [
                    'username' => 'another_user_' . uniqid(),
                    'email' => 'another_' . uniqid() . '@example.com',
                    'password_hash' => password_hash('securepass', PASSWORD_DEFAULT),
                ]);
                $results['insert'] = ['status' => 'success', 'message' => 'Users inserted.', 'ids' => [$insertedId1, $insertedId2]];
            } else {
                $results['insert'] = ['status' => 'info', 'message' => 'Skipped insert, enough users exist.'];
            }
        } catch (Exception $e) {
            $results['insert'] = ['status' => 'error', 'message' => 'Insert failed: ' . $e->getMessage()];
        }

        // 3. Select All Example
        try {
            $allUsers = $this->db->select('users', ['id', 'username', 'email', 'created_at'], [], 'created_at DESC');
            $results['select_all'] = ['status' => 'success', 'message' => 'All users retrieved.', 'data' => $allUsers, 'count' => count($allUsers)];
        } catch (Exception $e) {
            $results['select_all'] = ['status' => 'error', 'message' => 'Select all failed: ' . $e->getMessage()];
        }

        // 4. Select with WHERE Example
        try {
            $specificUser = $this->db->select('users', ['id', 'username', 'email'], ['username' => $allUsers[0]['username'] ?? 'nonexistent'], '', 1);
            $results['select_where'] = ['status' => 'success', 'message' => 'Specific user retrieved.', 'data' => $specificUser];
        } catch (Exception $e) {
            $results['select_where'] = ['status' => 'error', 'message' => 'Select with WHERE failed: ' . $e->getMessage()];
        }

        // 5. Update Example
        try {
            if (! empty($allUsers)) {
                $updatedRows = $this->db->update('users', ['email' => 'updated_' . uniqid() . '@example.com'], ['id' => $allUsers[0]['id']]);
                $results['update'] = ['status' => 'success', 'message' => "Updated {$updatedRows} row(s)."];
            } else {
                $results['update'] = ['status' => 'info', 'message' => 'Skipped update, no users to update.'];
            }
        } catch (Exception $e) {
            $results['update'] = ['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()];
        }

        // 6. Exists Example
        try {
            $userExists = $this->db->exists('users', ['username' => $allUsers[0]['username'] ?? 'nonexistent']);
            $results['exists'] = ['status' => 'success', 'message' => 'User exists check.', 'exists' => $userExists];
        } catch (Exception $e) {
            $results['exists'] = ['status' => 'error', 'message' => 'Exists check failed: ' . $e->getMessage()];
        }

        // 7. Count Example
        try {
            $userCount = $this->db->count('users');
            $results['count'] = ['status' => 'success', 'message' => 'Total user count.', 'count' => $userCount];
        } catch (Exception $e) {
            $results['count'] = ['status' => 'error', 'message' => 'Count failed: ' . $e->getMessage()];
        }

        // 8. Transaction Example
        try {
            if ($initialUserCount < 3) { // Ensure we don't insert too many times
                $transactionResult = $this->db->transaction(function (Database $db) {
                    $db->insert('users', [
                        'username' => 'transaction_user_' . uniqid(),
                        'email' => 'transaction_' . uniqid() . '@example.com',
                        'password_hash' => password_hash('txpass', PASSWORD_DEFAULT),
                    ]);

                    // Simulate an error to test rollback
                    // throw new \Exception("Simulating transaction rollback");
                    return 'Transaction successful (or rolled back)';
                });
                $results['transaction'] = ['status' => 'success', 'message' => $transactionResult];
            }

        } catch (Exception $e) {
            $results['transaction'] = ['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()];
        }

        // 9. Delete Example (clean up some data)
        try {
            $deletedRows = $this->db->delete('users', ['username' => 'transaction_user_' . uniqid()]); // This will likely delete nothing unless the transaction user was committed
            $results['delete'] = ['status' => 'success', 'message' => "Deleted {$deletedRows} row(s)."];
        } catch (Exception $e) {
            $results['delete'] = ['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()];
        }

        // 10. Table Exists Example
        try {
            $tableExists = $this->db->tableExists('users');
            $results['table_exists'] = ['status' => 'success', 'message' => 'Users table exists check.', 'exists' => $tableExists];
        } catch (Exception $e) {
            $results['table_exists'] = ['status' => 'error', 'message' => 'Table exists check failed: ' . $e->getMessage()];
        }

        // 11. Get Table Columns Example
        try {
            $columns = $this->db->getTableColumns('users');
            $results['get_table_columns'] = ['status' => 'success', 'message' => 'Users table columns retrieved.', 'columns' => $columns];
        } catch (Exception $e) {
            $results['get_table_columns'] = ['status' => 'error', 'message' => 'Get table columns failed: ' . $e->getMessage()];
        }

        // 12. Get All Tables Example
        try {
            $tables = $this->db->getTables();
            $results['get_all_tables'] = ['status' => 'success', 'message' => 'All tables retrieved.', 'tables' => $tables];
        } catch (Exception $e) {
            $results['get_all_tables'] = ['status' => 'error', 'message' => 'Get all tables failed: ' . $e->getMessage()];
        }

        return (new Response)->json($results, 200);
    }
}
