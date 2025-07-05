<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use SquareRouting\Core\Database\ColumnType;
use SquareRouting\Core\Database\Table;
use SquareRouting\Core\Database\ForeignKey;
use SquareRouting\Core\Database\ForeignKeyAction;

class Scheme
{
    public function account(): Table
    {
        $account = new Table('users');

        // Define columns
        $account->id = ColumnType::INT;
        $account->email = ColumnType::VARCHAR;
        $account->password = ColumnType::VARCHAR;
        $account->username = ColumnType::VARCHAR;
        $account->status = ColumnType::VARCHAR;
        $account->email_verified = ColumnType::BOOLEAN;
        $account->email_verification_token = ColumnType::VARCHAR;
        $account->reset_token = ColumnType::VARCHAR;
        $account->reset_token_expires = ColumnType::DATETIME;
        $account->remember_token = ColumnType::VARCHAR;
        $account->last_login = ColumnType::DATETIME;
        $account->created_at = ColumnType::DATETIME;
        $account->updated_at = ColumnType::DATETIME;

        // Configure id column
        $account->id->autoIncrement = true;

        // Configure email column
        $account->email->length = 255;
        $account->email->nullable = false;
        $account->email->unique = true;

        // Configure password column
        $account->password->length = 255;
        $account->password->nullable = false;

        // Configure username column
        $account->username->length = 100;
        $account->username->nullable = false;
        $account->username->unique = true;

        // Configure status column
        $account->status->length = 20;
        $account->status->nullable = false;
        $account->status->default = 'active';

        // Configure email verification
        $account->email_verified->nullable = false;
        $account->email_verified->default = false;
        $account->email_verification_token->length = 64;
        $account->email_verification_token->nullable = true;

        // Configure reset token
        $account->reset_token->length = 64;
        $account->reset_token->nullable = true;
        $account->reset_token_expires->nullable = true;

        // Configure remember token
        $account->remember_token->length = 64;
        $account->remember_token->nullable = true;

        // Configure timestamps
        $account->last_login->nullable = true;
        $account->created_at->nullable = false;
        $account->created_at->default = 'CURRENT_TIMESTAMP';
        $account->updated_at->nullable = true;

        // Create the table
        // $this->db->createTableIfNotExists($users);
        return $account;
    }

    public function configuration(): Table
    {
        $configuration = new Table('configurations');

        // Define columns
        $configuration->id = ColumnType::INT;
        $configuration->name = ColumnType::VARCHAR;
        $configuration->value = ColumnType::TEXT;
        $configuration->default_value = ColumnType::TEXT;
        $configuration->label = ColumnType::VARCHAR;
        $configuration->description = ColumnType::TEXT;
        $configuration->type = ColumnType::VARCHAR;
        $configuration->created_at = ColumnType::DATETIME;
        $configuration->updated_at = ColumnType::DATETIME;

        // Configure id column
        $configuration->id->autoIncrement = true;

        // Configure key column
        $configuration->name->length = 255;
        $configuration->name->nullable = false;
        $configuration->name->unique = true;

        // Configure value column (nullable for complex data types)
        $configuration->value->nullable = true;

        // Configure default_value column
        $configuration->default_value->nullable = true;

        // Configure label column
        $configuration->label->length = 255;
        $configuration->label->nullable = true;

        // Configure description column
        $configuration->description->nullable = true;

        // Configure type column (data type info)
        $configuration->type->length = 50;
        $configuration->type->nullable = false;
        $configuration->type->default = 'string';

        // Configure timestamps
        $configuration->created_at->nullable = false;
        $configuration->created_at->default = 'CURRENT_TIMESTAMP';

        $configuration->updated_at->nullable = true;

        return $configuration;
    }
    
    public function languages(): Table
    {
        $languages = new Table('languages');

        $languages->id = ColumnType::INT;
        $languages->code = ColumnType::VARCHAR; // e.g., 'en', 'de', 'fr'
        $languages->name = ColumnType::VARCHAR; // e.g., 'English', 'Deutsch'
        $languages->is_active = ColumnType::BOOLEAN;
        $languages->is_default = ColumnType::BOOLEAN;
        $languages->created_at = ColumnType::DATETIME;
        $languages->updated_at = ColumnType::DATETIME;

        $languages->id->autoIncrement = true;
        $languages->code->length = 10;
        $languages->code->nullable = false;
        $languages->code->unique = true;
        $languages->name->length = 100;
        $languages->name->nullable = false;
        $languages->is_active->nullable = false;
        $languages->is_active->default = true;
        $languages->is_default->nullable = false;
        $languages->is_default->default = false;
        $languages->created_at->nullable = false;
        $languages->created_at->default = 'CURRENT_TIMESTAMP';
        $languages->updated_at->nullable = true;

        return $languages;
    }

    public function pages(): Table
    {
        $pages = new Table('pages');

        $pages->id = ColumnType::INT;
        $pages->slug = ColumnType::VARCHAR;
        $pages->template = ColumnType::VARCHAR;
        $pages->is_published = ColumnType::BOOLEAN;
        $pages->created_at = ColumnType::DATETIME;
        $pages->updated_at = ColumnType::DATETIME;

        $pages->id->autoIncrement = true;
        $pages->slug->length = 255;
        $pages->slug->nullable = false;
        $pages->slug->unique = true;
        $pages->template->length = 100;
        $pages->template->nullable = false;
        $pages->template->default = 'default';
        $pages->is_published->nullable = false;
        $pages->is_published->default = false;
        $pages->created_at->nullable = false;
        $pages->created_at->default = 'CURRENT_TIMESTAMP';
        $pages->updated_at->nullable = true;

        return $pages;
    }

    public function pageTranslations(): Table
    {
        $languages = $this->languages(); // Assuming languages table is defined
        $pages = $this->pages(); // Assuming pages table is defined

        $pageTranslations = new Table('page_translations');

        $pageTranslations->id = ColumnType::INT;
        $pageTranslations->page_id = ColumnType::INT;
        $pageTranslations->language_id = ColumnType::INT;
        $pageTranslations->title = ColumnType::VARCHAR;
        $pageTranslations->content = ColumnType::TEXT;
        $pageTranslations->meta_title = ColumnType::VARCHAR;
        $pageTranslations->meta_description = ColumnType::TEXT;
        $pageTranslations->created_at = ColumnType::DATETIME;
        $pageTranslations->updated_at = ColumnType::DATETIME;

        $pageTranslations->id->autoIncrement = true;
        $pageTranslations->page_id->foreignKey = new ForeignKey($pages, $pages->id);
        $pageTranslations->page_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $pageTranslations->page_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $pageTranslations->page_id->nullable = false;

        $pageTranslations->language_id->foreignKey = new ForeignKey($languages, $languages->id);
        $pageTranslations->language_id->foreignKey->onDelete = ForeignKeyAction::RESTRICT;
        $pageTranslations->language_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $pageTranslations->language_id->nullable = false;

        $pageTranslations->title->length = 255;
        $pageTranslations->title->nullable = false;
        $pageTranslations->content->nullable = true;
        $pageTranslations->meta_title->length = 255;
        $pageTranslations->meta_title->nullable = true;
        $pageTranslations->meta_description->nullable = true;
        $pageTranslations->created_at->nullable = false;
        $pageTranslations->created_at->default = 'CURRENT_TIMESTAMP';
        $pageTranslations->updated_at->nullable = true;

        return $pageTranslations;
    }

    public function posts(): Table
    {
        $posts = new Table('posts');

        $posts->id = ColumnType::INT;
        $posts->author_id = ColumnType::INT; // Assuming a users table exists
        $posts->category_id = ColumnType::INT; // Assuming a categories table exists
        $posts->slug = ColumnType::VARCHAR;
        $posts->is_published = ColumnType::BOOLEAN;
        $posts->published_at = ColumnType::DATETIME;
        $posts->created_at = ColumnType::DATETIME;
        $posts->updated_at = ColumnType::DATETIME;

        $posts->id->autoIncrement = true;
        // Assuming 'users' table is defined in account() method
        $users = $this->account();
        $posts->author_id->foreignKey = new ForeignKey($users, $users->id);
        $posts->author_id->foreignKey->onDelete = ForeignKeyAction::SET_NULL;
        $posts->author_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $posts->author_id->nullable = true;

        // Categories table
        $categories = $this->categories();
        $posts->category_id->foreignKey = new ForeignKey($categories, $categories->id);
        $posts->category_id->foreignKey->onDelete = ForeignKeyAction::SET_NULL;
        $posts->category_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $posts->category_id->nullable = true;

        $posts->slug->length = 255;
        $posts->slug->nullable = false;
        $posts->slug->unique = true;
        $posts->is_published->nullable = false;
        $posts->is_published->default = false;
        $posts->published_at->nullable = true;
        $posts->created_at->nullable = false;
        $posts->created_at->default = 'CURRENT_TIMESTAMP';
        $posts->updated_at->nullable = true;

        return $posts;
    }

    public function postTranslations(): Table
    {
        $languages = $this->languages();
        $posts = $this->posts();

        $postTranslations = new Table('post_translations');

        $postTranslations->id = ColumnType::INT;
        $postTranslations->post_id = ColumnType::INT;
        $postTranslations->language_id = ColumnType::INT;
        $postTranslations->title = ColumnType::VARCHAR;
        $postTranslations->content = ColumnType::TEXT;
        $postTranslations->excerpt = ColumnType::TEXT;
        $postTranslations->meta_title = ColumnType::VARCHAR;
        $postTranslations->meta_description = ColumnType::TEXT;
        $postTranslations->created_at = ColumnType::DATETIME;
        $postTranslations->updated_at = ColumnType::DATETIME;

        $postTranslations->id->autoIncrement = true;
        $postTranslations->post_id->foreignKey = new ForeignKey($posts, $posts->id);
        $postTranslations->post_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $postTranslations->post_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $postTranslations->post_id->nullable = false;

        $postTranslations->language_id->foreignKey = new ForeignKey($languages, $languages->id);
        $postTranslations->language_id->foreignKey->onDelete = ForeignKeyAction::RESTRICT;
        $postTranslations->language_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $postTranslations->language_id->nullable = false;

        $postTranslations->title->length = 255;
        $postTranslations->title->nullable = false;
        $postTranslations->content->nullable = true;
        $postTranslations->excerpt->nullable = true;
        $postTranslations->meta_title->length = 255;
        $postTranslations->meta_title->nullable = true;
        $postTranslations->meta_description->nullable = true;
        $postTranslations->created_at->nullable = false;
        $postTranslations->created_at->default = 'CURRENT_TIMESTAMP';
        $postTranslations->updated_at->nullable = true;

        return $postTranslations;
    }

    public function tags(): Table
    {
        $tags = new Table('tags');

        $tags->id = ColumnType::INT;
        $tags->name = ColumnType::VARCHAR;

        $tags->id->autoIncrement = true;
        $tags->name->length = 100;
        $tags->name->nullable = false;
        $tags->name->unique = true;

        return $tags;
    }

    public function postTags(): Table
    {
        $posts = $this->posts();
        $tags = $this->tags();

        $postTags = new Table('post_tags');

        $postTags->id = ColumnType::INT;
        $postTags->post_id = ColumnType::INT;
        $postTags->tag_id = ColumnType::INT;
        $postTags->created_at = ColumnType::DATETIME;

        $postTags->id->autoIncrement = true;
        $postTags->post_id->foreignKey = new ForeignKey($posts, $posts->id);
        $postTags->post_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $postTags->post_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $postTags->post_id->nullable = false;

        $postTags->tag_id->foreignKey = new ForeignKey($tags, $tags->id);
        $postTags->tag_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $postTags->tag_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $postTags->tag_id->nullable = false;

        $postTags->created_at->nullable = false;
        $postTags->created_at->default = 'CURRENT_TIMESTAMP';

        return $postTags;
    }

    public function categories(): Table
    {
        $categories = new Table('categories');

        $categories->id = ColumnType::INT;
        $categories->name = ColumnType::VARCHAR;
        $categories->slug = ColumnType::VARCHAR;
        $categories->created_at = ColumnType::DATETIME;
        $categories->updated_at = ColumnType::DATETIME;

        $categories->id->autoIncrement = true;
        $categories->name->length = 100;
        $categories->name->nullable = false;
        $categories->name->unique = true;
        $categories->slug->length = 100;
        $categories->slug->nullable = false;
        $categories->slug->unique = true;
        $categories->created_at->nullable = false;
        $categories->created_at->default = 'CURRENT_TIMESTAMP';
        $categories->updated_at->nullable = true;

        return $categories;
    }

    public function comments(): Table
    {
        $comments = new Table('comments');

        $comments->id = ColumnType::INT;
        $comments->user_id = ColumnType::INT; // Optional, if comments can be anonymous
        $comments->post_id = ColumnType::INT; // For comments on blog posts
        $comments->page_id = ColumnType::INT; // For comments on pages
        $comments->parent_id = ColumnType::INT; // For nested comments
        $comments->content = ColumnType::TEXT;
        $comments->status = ColumnType::VARCHAR; // e.g., 'pending', 'approved', 'spam'
        $comments->created_at = ColumnType::DATETIME;
        $comments->updated_at = ColumnType::DATETIME;

        $comments->id->autoIncrement = true;
        $users = $this->account();
        $comments->user_id->foreignKey = new ForeignKey($users, $users->id);
        $comments->user_id->foreignKey->onDelete = ForeignKeyAction::SET_NULL;
        $comments->user_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $comments->user_id->nullable = true;

        $posts = $this->posts();
        $comments->post_id->foreignKey = new ForeignKey($posts, $posts->id);
        $comments->post_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $comments->post_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $comments->post_id->nullable = true;

        $pages = $this->pages();
        $comments->page_id->foreignKey = new ForeignKey($pages, $pages->id);
        $comments->page_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $comments->page_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $comments->page_id->nullable = true;

        $comments->parent_id->foreignKey = new ForeignKey($comments, $comments->id);
        $comments->parent_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $comments->parent_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $comments->parent_id->nullable = true;

        $comments->content->nullable = false;
        $comments->status->length = 50;
        $comments->status->nullable = false;
        $comments->status->default = 'pending';
        $comments->created_at->nullable = false;
        $comments->created_at->default = 'CURRENT_TIMESTAMP';
        $comments->updated_at->nullable = true;

        return $comments;
    }

    public function navigationMenus(): Table
    {
        $navigationMenus = new Table('navigation_menus');

        $navigationMenus->id = ColumnType::INT;
        $navigationMenus->name = ColumnType::VARCHAR; // e.g., 'main-menu', 'footer-menu'
        $navigationMenus->slug = ColumnType::VARCHAR;
        $navigationMenus->created_at = ColumnType::DATETIME;
        $navigationMenus->updated_at = ColumnType::DATETIME;

        $navigationMenus->id->autoIncrement = true;
        $navigationMenus->name->length = 100;
        $navigationMenus->name->nullable = false;
        $navigationMenus->name->unique = true;
        $navigationMenus->slug->length = 100;
        $navigationMenus->slug->nullable = false;
        $navigationMenus->slug->unique = true;
        $navigationMenus->created_at->nullable = false;
        $navigationMenus->created_at->default = 'CURRENT_TIMESTAMP';
        $navigationMenus->updated_at->nullable = true;

        return $navigationMenus;
    }

    public function navigationItems(): Table
    {
        $navigationMenus = $this->navigationMenus();
        $pages = $this->pages();
        $posts = $this->posts();

        $navigationItems = new Table('navigation_items');

        $navigationItems->id = ColumnType::INT;
        $navigationItems->menu_id = ColumnType::INT;
        $navigationItems->parent_id = ColumnType::INT; // For nested menu items
        $navigationItems->type = ColumnType::VARCHAR; // e.g., 'page', 'post', 'custom_link'
        $navigationItems->target_id = ColumnType::INT; // ID of page/post if type is page/post
        $navigationItems->custom_url = ColumnType::VARCHAR;
        $navigationItems->order = ColumnType::INT;
        $navigationItems->created_at = ColumnType::DATETIME;
        $navigationItems->updated_at = ColumnType::DATETIME;

        $navigationItems->id->autoIncrement = true;
        $navigationItems->menu_id->foreignKey = new ForeignKey($navigationMenus, $navigationMenus->id);
        $navigationItems->menu_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $navigationItems->menu_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $navigationItems->menu_id->nullable = false;

        $navigationItems->parent_id->foreignKey = new ForeignKey($navigationItems, $navigationItems->id);
        $navigationItems->parent_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $navigationItems->parent_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $navigationItems->parent_id->nullable = true;

        $navigationItems->type->length = 50;
        $navigationItems->type->nullable = false;
        $navigationItems->type->default = 'custom_link';

        // Target ID can refer to a page or a post, or be null for custom links
        $navigationItems->target_id->nullable = true;

        $navigationItems->custom_url->length = 500;
        $navigationItems->custom_url->nullable = true;

        $navigationItems->order->nullable = false;
        $navigationItems->order->default = 0;
        $navigationItems->created_at->nullable = false;
        $navigationItems->created_at->default = 'CURRENT_TIMESTAMP';
        $navigationItems->updated_at->nullable = true;

        return $navigationItems;
    }

    public function navigationItemTranslations(): Table
    {
        $languages = $this->languages();
        $navigationItems = $this->navigationItems();

        $navigationItemTranslations = new Table('navigation_item_translations');

        $navigationItemTranslations->id = ColumnType::INT;
        $navigationItemTranslations->item_id = ColumnType::INT;
        $navigationItemTranslations->language_id = ColumnType::INT;
        $navigationItemTranslations->title = ColumnType::VARCHAR;
        $navigationItemTranslations->created_at = ColumnType::DATETIME;
        $navigationItemTranslations->updated_at = ColumnType::DATETIME;

        $navigationItemTranslations->id->autoIncrement = true;
        $navigationItemTranslations->item_id->foreignKey = new ForeignKey($navigationItems, $navigationItems->id);
        $navigationItemTranslations->item_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $navigationItemTranslations->item_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $navigationItemTranslations->item_id->nullable = false;

        $navigationItemTranslations->language_id->foreignKey = new ForeignKey($languages, $languages->id);
        $navigationItemTranslations->language_id->foreignKey->onDelete = ForeignKeyAction::RESTRICT;
        $navigationItemTranslations->language_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $navigationItemTranslations->language_id->nullable = false;

        $navigationItemTranslations->title->length = 255;
        $navigationItemTranslations->title->nullable = false;
        $navigationItemTranslations->created_at->nullable = false;
        $navigationItemTranslations->created_at->default = 'CURRENT_TIMESTAMP';
        $navigationItemTranslations->updated_at->nullable = true;

        return $navigationItemTranslations;
    }
}
