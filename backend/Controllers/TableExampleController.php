<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Database;
use SquareRouting\Core\Database\ColumnType;
use SquareRouting\Core\Database\ForeignKey;
use SquareRouting\Core\Database\ForeignKeyAction;
use SquareRouting\Core\Database\Table;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response;

class TableExampleController
{
    private Database $db;

    public function __construct(DependencyContainer $container)
    {
        $this->db = $container->get(Database::class);
    }

    public function tableExample(): Response
    {
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
      $categories->name->unique = true;
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
      $manufacturers->name->unique = true;
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
      $products->sku->unique = true;
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

        return (new Response)->html('Created categories table');
    }
}