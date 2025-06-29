<?php
namespace SquareRouting\Controllers;

use PDOException;
use SquareRouting\Core\Database\Column;
use SquareRouting\Core\Database\DatabaseDialect;
use SquareRouting\Core\Database\ForeignKey;
use SquareRouting\Core\Database\ForeignKeyAction;
use SquareRouting\Core\Database\ColumnType;
use SquareRouting\Core\Database\Table;
use SquareRouting\Core\Language;
use SquareRouting\Core\View;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Account;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response; // Important for the Return Type Hint
use SquareRouting\Core\Cache;
use SquareRouting\Core\Database;
use PDO;
use SquareRouting\Core\Validation\Validator;
use SquareRouting\Core\Validation\Rules\Required;
use SquareRouting\Core\Validation\Rules\Email;
use SquareRouting\Core\Validation\Rules\Min;
use SquareRouting\Core\Validation\Rules\Max;
use SquareRouting\Core\Validation\Rules\In;
use SquareRouting\Core\Validation\Rules\IsArray;
use SquareRouting\Core\Validation\Rules\Json;

class ExampleController  {
  public Request $request;
  public Cache $cache;
  public DotEnv $dotEnv;
  private Database $db;
  public View $view;
  public Language $language;
  public RateLimiter $rateLimiter;
  public Account $account;

  public function __construct(DependencyContainer $container) {
   $this->cache = $container->get(Cache::class);
   $this->request = $container->get(Request::class);
   $this->dotEnv = $container->get(DotEnv::class);
   $this->db = $container->get(Database::class);
   $this->view = $container->get(View::class);
   $this->rateLimiter = $container->get(RateLimiter::class);
   $this->language = $container->get(Language::class);
   $this->account = $container->get(Account::class);
  }

 public function tableExample(): Response {
// categories 
$categories = new Table('categories');
$categories->id = ColumnType::INT;
$categories->name = ColumnType::VARCHAR;
$categories->description = ColumnType::TEXT;
$categories->isActive = ColumnType::BOOLEAN;
$categories->createdAt = ColumnType::DATETIME;
$categories->updatedAt = ColumnType::DATETIME;

$categories->id->autoIncrement = true;
$categories->name->length = 100;
$categories->name->nullable = false;
$categories->description->nullable = true;
$categories->description->default = '';
$categories->isActive->nullable = false;
$categories->isActive->default = true;
$categories->createdAt->nullable = false;
$categories->createdAt->default = 'CURRENT_TIMESTAMP';
$categories->updatedAt->nullable = true;

// manufacturers 
$manufacturers = new Table('manufacturers');
$manufacturers->id = ColumnType::INT;
$manufacturers->name = ColumnType::VARCHAR;
$manufacturers->email = ColumnType::VARCHAR;
$manufacturers->website = ColumnType::VARCHAR;
$manufacturers->country = ColumnType::VARCHAR;

$manufacturers->id->autoIncrement = true;
$manufacturers->name->length = 150;
$manufacturers->name->nullable = false;
$manufacturers->email->length = 255;
$manufacturers->email->nullable = true;
$manufacturers->website->length = 500;
$manufacturers->website->nullable = true;
$manufacturers->country->length = 100;
$manufacturers->country->nullable = false;
$manufacturers->country->default = 'Germany';

// products
$products = new Table('products');
$products->id = ColumnType::INT;
$products->categoryId = ColumnType::INT;
$products->manufacturerId = ColumnType::INT;
$products->sku = ColumnType::VARCHAR;
$products->name = ColumnType::VARCHAR;
$products->price = ColumnType::DECIMAL;
$products->weight = ColumnType::DECIMAL;
$products->inStock = ColumnType::BOOLEAN;
$products->stockCount = ColumnType::INT;
$products->description = ColumnType::TEXT;
$products->metadata = ColumnType::JSON;

$products->id->autoIncrement = true;
// Category Foreign Key - CASCADE (if category is deleted, product is also deleted)
$products->categoryId->foreignKey = new ForeignKey($categories, $categories->id);
$products->categoryId->foreignKey->onDelete = ForeignKeyAction::CASCADE;
$products->categoryId->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
$products->categoryId->nullable = false;

// Manufacturer Foreign Key - SET_NULL (if manufacturer is deleted, set to NULL)
$products->manufacturerId->foreignKey = new ForeignKey($manufacturers, $manufacturers->id);
$products->manufacturerId->foreignKey->onDelete = ForeignKeyAction::SET_NULL;
$products->manufacturerId->foreignKey->onUpdate = ForeignKeyAction::RESTRICT;
$products->manufacturerId->nullable = true;

$products->sku->length = 50;
$products->sku->nullable = false;
$products->name->length = 255;
$products->name->nullable = false;
$products->price->nullable = false;
$products->price->default = 0.00;
$products->weight->nullable = true;
$products->inStock->nullable = false;
$products->inStock->default = true;
$products->stockCount->nullable = false;
$products->stockCount->default = 0;
$products->description->nullable = true;
$products->metadata->nullable = true;

// Orders Table
$orders = new Table('orders');
$orders->id = ColumnType::INT;
$orders->customerId = ColumnType::INT;
$orders->orderNumber = ColumnType::VARCHAR;
$orders->status = ColumnType::VARCHAR;
$orders->totalAmount = ColumnType::DECIMAL;
$orders->orderDate = ColumnType::DATETIME;
$orders->shippedDate = ColumnType::DATETIME;

$orders->id->autoIncrement = true;
$orders->customerId->nullable = false;
$orders->orderNumber->length = 100;
$orders->orderNumber->nullable = false;
$orders->status->length = 50;
$orders->status->nullable = false;
$orders->status->default = 'pending';
$orders->totalAmount->nullable = false;
$orders->totalAmount->default = 0.00;
$orders->orderDate->nullable = false;
$orders->orderDate->default = 'CURRENT_TIMESTAMP';
$orders->shippedDate->nullable = true;

// Order Items - Junction table with two Foreign Keys
$orderItems = new Table('order_items');
$orderItems->id = ColumnType::INT;
$orderItems->orderId = ColumnType::INT;
$orderItems->productId = ColumnType::INT;
$orderItems->quantity = ColumnType::INT;
$orderItems->unitPrice = ColumnType::DECIMAL;
$orderItems->totalPrice = ColumnType::DECIMAL;

$orderItems->id->autoIncrement = true;

// Order Foreign Key - CASCADE (if order is deleted, items are also deleted)
$orderItems->orderId->foreignKey = new ForeignKey($orders, $orders->id);
$orderItems->orderId->foreignKey->onDelete = ForeignKeyAction::CASCADE;
$orderItems->orderId->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
$orderItems->orderId->nullable = false;

// Product Foreign Key - RESTRICT (product cannot be deleted if still in orders)
$orderItems->productId->foreignKey = new ForeignKey($products, $products->id);
$orderItems->productId->foreignKey->onDelete = ForeignKeyAction::RESTRICT;
$orderItems->productId->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
$orderItems->productId->nullable = false;

$orderItems->quantity->nullable = false;
$orderItems->quantity->default = 1;
$orderItems->unitPrice->nullable = false;
$orderItems->unitPrice->default = 0.00;
$orderItems->totalPrice->nullable = false;
$orderItems->totalPrice->default = 0.00;


$this->db->createTableIfNotExists($categories);



return (new Response)->html("Created categories table");

 } 


 public function home(): Response
 {
    return (new Response)->html("hello");
 }

  public function someTest(int $mynum): Response {
    $data = [
        'status' => 'success',
        'received_number' => $mynum,
        'message' => 'This is a proper JSON response!'
    ];
    
    return (new Response)->json($data, 200);
  }
  
  public function showHtmlPage(): Response {
      $html = "<h1>Hello World!</h1><p>This is an HTML page.</p>
               <form action=\"/post-example\" method=\"POST\">
                   <button type=\"submit\">Send POST Request</button>
               </form>";
      return (new Response)->html($html);
  }


  public function handlePostRequest(): Response {
    $data = $this->request->post();
    return (new Response)->json(['status' => 'success', 'message' => 'POST request received!', 'data' => $data], 200);
  }

  public function handlePutRequest(int $id): Response
  {
      $data = $this->request->getJson();
      $response = new Response();
      return $response->json(['message' => "Put request received for ID: {$id}", 'data' => $data]);
  }

  public function handleDeleteRequest(int $id): Response
  {
      $response = new Response();
      return $response->json(['message' => "Delete request received for ID: {$id}"]);
  }

  public function handlePatchRequest(int $id): Response
  {
      $data = $this->request->getJson();
      $response = new Response();
      return $response->json(['message' => "Patch request received for ID: {$id}", 'data' => $data]);
  }


  public function redirectToGoogle(): Response {
      return (new Response)->redirect('https://www.google.com');
  }

  public function rateLimiterExample(): Response {
    $clientId = $this->request->getClientIp(); // Get client IP address
    $key = 'api_access'; // Define a key for the rate limit

    $this->rateLimiter->setLimit($key, 5, 60); // 5 attempts per 60 seconds

    if ($this->rateLimiter->isLimitExceeded($key, $clientId)) {
        $remainingTime = $this->rateLimiter->getRemainingTimeToReset($key, $clientId);
        return (new Response)->json(['status' => 'error', 'message' => 'Rate limit exceeded. Try again in ' . $remainingTime . ' seconds.'], 429);
    }

    $this->rateLimiter->registerAttempt($key, $clientId);
    $remainingAttempts = $this->rateLimiter->getRemainingAttempts($key, $clientId);

    return (new Response)->json(['status' => 'success', 'message' => 'API access granted.', 'remaining_attempts' => $remainingAttempts], 200);
  }

  public function cacheExample(): Response {
    $cacheKey = 'my_cached_data';
    $prefix = 'example_prefix';
    $ttl = 15; // Cache for 15 seconds

    $data = $this->cache->get($prefix, $cacheKey, function() {
        sleep(2); // Simulate a delay
        return [
            'message' => 'Data fetched from source at ' . date('Y-m-d H:i:s'),
            'generated_at' => time() // Store Unix timestamp
        ];
    }, $ttl);

    // Helper logic to display the remaining time
    $generatedAt = $data['generated_at'] ?? null;
    $expiresAt = null;
    $remainingSeconds = null;
    if ($generatedAt !== null) {
        $expiresAt = $generatedAt + $ttl;
        $remainingSeconds = $expiresAt - time();
        if ($remainingSeconds < 0) {
            $remainingSeconds = 0; // Cache has already expired
        }
    }

    return (new Response)->json([
        'status' => 'success',
        'data' => $data,
        'source' => 'cache',
        'remaining_seconds_until_expiry' => $remainingSeconds
    ], 200);
  }

  public function dashboardExample(?string $location): Response {
    // location for instance home, home/settings, etc.
    $availableSections = [
        'home' => 'Welcome to the Dashboard! This is your home page.',
        'settings' => 'Dashboard Settings. Adjust your preferences here.',
        'profile' => 'User Profile. View and edit your profile information.'
    ];

    $htmlContent = "";

    switch ($location) {
        case 'home':
            $htmlContent = "<h1>Welcome to the Dashboard!</h1><p>{$availableSections['home']}</p>";
            break;
        case 'settings':
            $htmlContent = "<h1>Dashboard Settings</h1><p>{$availableSections['settings']}</p>";
            break;
        case 'profile':
            $htmlContent = "<h1>User Profile</h1><p>{$availableSections['profile']}</p>";
            break;
        default:
            $htmlContent = "<h1>Dashboard Overview</h1><p>Available sections:</p><ul>";
            foreach ($availableSections as $key => $value) {
                $htmlContent .= "<li><a href=\"/dashboard/{$key}\">" . ucfirst($key) . "</a></li>";
            }
            $htmlContent .= "</ul>";
            break;
    }

    return (new Response)->html($htmlContent);
  }

  public function filterTest(): Response {
    return (new Response)->html(" Filter Test ");
  }
  
  function envExample(): Response {
    $testValue = $this->dotEnv->get("TESTVALUE");
    return (new Response)->html("The .env value is: ".$testValue);
  }

  public function showValidatorExample(): Response {
    $data = [
        'pageTitle' => 'Validator Example Form',
    ];
    $this->view->setMultiple($data);
    $output = $this->view->render("validator_example.tpl");
    return (new Response)->html($output);
  }

  public function validateExample(): Response {
    $data = $this->request->json();

    // 2. The rules for the data
    $rules = [
        'username' => [new Required(), new Min(5)],
        'password' => [new Required(), new Min(8)],
        'status' => [new Required(), new In(['active', 'inactive', 'pending'])],

        // Nested validation using dot notation
        'contact.email' => [new Required(), new Email()],
        'contact.address.city' => [new Required()],

        // Array validation using the '*' wildcard
        'tags' => [new IsArray(), new Min(1)], // The 'tags' field itself must be an array with at least 1 item.
        'tags.*.id' => [new Required()], // Rule for each item in the 'tags' array
        'tags.*.name' => [new Required(), new Min(3)],

        // JSON validation
        'metadata_json' => [new Json()],
        'invalid_json' => [new Json()],
    ];

    // 3. Create a validator instance and run it
    $validator = new Validator($data, $rules);

    if ($validator->fails()) {
        return (new Response)->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'data_used_for_validation' => $data
        ], 400);
    } else {
        return (new Response)->json([
            'status' => 'success',
            'message' => 'Validation passed',
            'validated_data' => $validator->validated(),
            'data_used_for_validation' => $data
        ], 200);
    }
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
        } catch (\Exception $e) {
            $results['create_table'] = ['status' => 'error', 'message' => 'Create table failed: ' . $e->getMessage()];
        }

        // 2. Insert Example
        try {
            $initialUserCount = $this->db->count('users');

            if ($initialUserCount < 3) { // Ensure we don't insert too many times
                $insertedId1 = $this->db->insert('users', [
                    'username' => 'test_user_' . uniqid(),
                    'email' => 'test_' . uniqid() . '@example.com',
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT)
                ]);
                $insertedId2 = $this->db->insert('users', [
                    'username' => 'another_user_' . uniqid(),
                    'email' => 'another_' . uniqid() . '@example.com',
                    'password_hash' => password_hash('securepass', PASSWORD_DEFAULT)
                ]);
                $results['insert'] = ['status' => 'success', 'message' => 'Users inserted.', 'ids' => [$insertedId1, $insertedId2]];
            } else {
                $results['insert'] = ['status' => 'info', 'message' => 'Skipped insert, enough users exist.'];
            }
        } catch (\Exception $e) {
            $results['insert'] = ['status' => 'error', 'message' => 'Insert failed: ' . $e->getMessage()];
        }

        // 3. Select All Example
        try {
            $allUsers = $this->db->select('users', ['id', 'username', 'email', 'created_at'], [], 'created_at DESC');
            $results['select_all'] = ['status' => 'success', 'message' => 'All users retrieved.', 'data' => $allUsers, 'count' => count($allUsers)];
        } catch (\Exception $e) {
            $results['select_all'] = ['status' => 'error', 'message' => 'Select all failed: ' . $e->getMessage()];
        }

        // 4. Select with WHERE Example
        try {
            $specificUser = $this->db->select('users', ['id', 'username', 'email'], ['username' => $allUsers[0]['username'] ?? 'nonexistent'], '', 1);
            $results['select_where'] = ['status' => 'success', 'message' => 'Specific user retrieved.', 'data' => $specificUser];
        } catch (\Exception $e) {
            $results['select_where'] = ['status' => 'error', 'message' => 'Select with WHERE failed: ' . $e->getMessage()];
        }

        // 5. Update Example
        try {
            if (!empty($allUsers)) {
                $updatedRows = $this->db->update('users', ['email' => 'updated_' . uniqid() . '@example.com'], ['id' => $allUsers[0]['id']]);
                $results['update'] = ['status' => 'success', 'message' => "Updated {$updatedRows} row(s)."];
            } else {
                $results['update'] = ['status' => 'info', 'message' => 'Skipped update, no users to update.'];
            }
        } catch (\Exception $e) {
            $results['update'] = ['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()];
        }

        // 6. Exists Example
        try {
            $userExists = $this->db->exists('users', ['username' => $allUsers[0]['username'] ?? 'nonexistent']);
            $results['exists'] = ['status' => 'success', 'message' => 'User exists check.', 'exists' => $userExists];
        } catch (\Exception $e) {
            $results['exists'] = ['status' => 'error', 'message' => 'Exists check failed: ' . $e->getMessage()];
        }

        // 7. Count Example
        try {
            $userCount = $this->db->count('users');
            $results['count'] = ['status' => 'success', 'message' => 'Total user count.', 'count' => $userCount];
        } catch (\Exception $e) {
            $results['count'] = ['status' => 'error', 'message' => 'Count failed: ' . $e->getMessage()];
        }

        // 8. Transaction Example
        try {
            if ($initialUserCount < 3) { // Ensure we don't insert too many times
                            $transactionResult = $this->db->transaction(function (Database $db) {
                $db->insert('users', [
                    'username' => 'transaction_user_' . uniqid(),
                    'email' => 'transaction_' . uniqid() . '@example.com',
                    'password_hash' => password_hash('txpass', PASSWORD_DEFAULT)
                ]);
                // Simulate an error to test rollback
                // throw new \Exception("Simulating transaction rollback");
                return "Transaction successful (or rolled back)";
            });
            $results['transaction'] = ['status' => 'success', 'message' => $transactionResult];
            }

        } catch (\Exception $e) {
            $results['transaction'] = ['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()];
        }

        // 9. Delete Example (clean up some data)
        try {
            $deletedRows = $this->db->delete('users', ['username' => 'transaction_user_' . uniqid()]); // This will likely delete nothing unless the transaction user was committed
            $results['delete'] = ['status' => 'success', 'message' => "Deleted {$deletedRows} row(s)."];
        } catch (\Exception $e) {
            $results['delete'] = ['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()];
        }

        // 10. Table Exists Example
        try {
            $tableExists = $this->db->tableExists('users');
            $results['table_exists'] = ['status' => 'success', 'message' => 'Users table exists check.', 'exists' => $tableExists];
        } catch (\Exception $e) {
            $results['table_exists'] = ['status' => 'error', 'message' => 'Table exists check failed: ' . $e->getMessage()];
        }

        // 11. Get Table Columns Example
        try {
            $columns = $this->db->getTableColumns('users');
            $results['get_table_columns'] = ['status' => 'success', 'message' => 'Users table columns retrieved.', 'columns' => $columns];
        } catch (\Exception $e) {
            $results['get_table_columns'] = ['status' => 'error', 'message' => 'Get table columns failed: ' . $e->getMessage()];
        }

        // 12. Get All Tables Example
        try {
            $tables = $this->db->getTables();
            $results['get_all_tables'] = ['status' => 'success', 'message' => 'All tables retrieved.', 'tables' => $tables];
        } catch (\Exception $e) {
            $results['get_all_tables'] = ['status' => 'error', 'message' => 'Get all tables failed: ' . $e->getMessage()];
        }

        return (new Response)->json($results, 200);
    }


    public function templateExample(): Response {
        $data = [
            'pageTitle' => 'Template Engine Example',
            'greeting' => 'Hello',
            'userName' => 'World',
            'currentYear' => date('Y'),
            'currentTime' => date('H:i:s'),
            'features' => [
                ['name' => 'Variables', 'description' => 'Dynamic content display'],
                ['name' => 'Loops', 'description' => 'Iterating over data collections'],
                ['name' => 'Conditionals', 'description' => 'Displaying content based on logic'],
                ['name' => 'Includes', 'description' => 'Reusing template partials'],
                ['name' => 'Translations', 'description' => 'Multilingual support'],
                ['name' => 'Events', 'description' => 'Injecting dynamic content via callbacks'],
                ['name' => 'Caching', 'description' => 'Improved performance'],
                ['name' => 'Auto-escaping', 'description' => 'XSS protection by default'],
            ],
            'isAdmin' => true,
            'showExtraContent' => true,
            'rawHtml' => '<strong>This is raw HTML!</strong> <script>alert("Test XSS attempt!");</script>',
        ];

        $this->view->setMultiple($data);;
        $output = $this->view->render("demo.tpl");
        return (new Response)->html($output);
    }

    public function languageExample(): Response {
       return (new Response)->html($this->language->translate("user.profile", "foobar", 8));
    }

 
}

