#!/usr/bin/env python3
"""
PHP Schema Const Generator

Using strings for database operations in php is a pain.
This script reads a Scheme.php file and generates PHP classes with constants for:
1. Column names (ColumnName.php)
2. Table names (TableName.php)

This can make refactoring incredibly easy, it makes your code also more readable. 

Usage: python generate_consts.py
"""

import re
import os
from pathlib import Path
from typing import Set, Dict, List


class PhpConstGenerator:
    def __init__(self, scheme_file_path: str = "./Scheme.php"):
        self.scheme_file_path = scheme_file_path
        self.column_names: Set[str] = set()
        self.table_names: Set[str] = set()
        
    def read_scheme_file(self) -> str:
        """Read the Scheme.php file content"""
        try:
            with open(self.scheme_file_path, 'r', encoding='utf-8') as file:
                return file.read()
        except FileNotFoundError:
            raise FileNotFoundError(f"Scheme file not found: {self.scheme_file_path}")
    
    def extract_column_names(self, content: str) -> None:
        """Extract all column names from the scheme file"""
        # Pattern to match column definitions like: $table->column_name = ColumnType::TYPE;
        column_pattern = r'\$\w+->([a-zA-Z_][a-zA-Z0-9_]*)\s*='
        
        matches = re.findall(column_pattern, content)
        for match in matches:
            # Skip common variable names that aren't columns
            if match not in ['autoIncrement', 'length', 'nullable', 'unique', 'default', 'foreignKey', 'onDelete', 'onUpdate']:
                self.column_names.add(match)
    
    def extract_table_names(self, content: str) -> None:
        """Extract all table names from the scheme file"""
        # Pattern to match table instantiation like: new Table('table_name')
        table_pattern = r"new\s+Table\s*\(\s*['\"]([^'\"]+)['\"]\s*\)"
        
        matches = re.findall(table_pattern, content)
        for match in matches:
            self.table_names.add(match)
    
    def snake_to_constant(self, name: str) -> str:
        """Convert snake_case to CONSTANT_CASE"""
        return name.upper()
    
    def snake_to_pascal(self, name: str) -> str:
        """Convert snake_case to PascalCase"""
        return ''.join(word.capitalize() for word in name.split('_'))
    
    def generate_column_const_class(self) -> str:
        """Generate PHP class with constants for column names"""
        sorted_columns = sorted(self.column_names)
        
        const_declarations = []
        for column in sorted_columns:
            constant_name = self.snake_to_constant(column)
            const_declarations.append(f"    const {constant_name} = '{column}';")
        
        class_content = f"""<?php

declare(strict_types=1);

namespace SquareRouting\\Core\\Scheme;

/**
 * Constants for database column names
 * Auto-generated from Scheme.php
 */
class ColumnName
{{
{chr(10).join(const_declarations)}
}}
"""
        return class_content
    
    def generate_table_const_class(self) -> str:
        """Generate PHP class with constants for table names"""
        sorted_tables = sorted(self.table_names)
        
        const_declarations = []
        for table in sorted_tables:
            constant_name = self.snake_to_constant(table)
            const_declarations.append(f"    const {constant_name} = '{table}';")
        
        class_content = f"""<?php

declare(strict_types=1);

namespace SquareRouting\\Core\\Scheme;

/**
 * Constants for database table names
 * Auto-generated from Scheme.php
 */
class TableName
{{
{chr(10).join(const_declarations)}
}}
"""
        return class_content
    
    def create_scheme_directory(self) -> None:
        """Create the Scheme directory if it doesn't exist"""
        scheme_dir = Path("./Scheme")
        scheme_dir.mkdir(exist_ok=True)
    
    def write_const_file(self, filename: str, content: str) -> None:
        """Write const class content to file"""
        self.create_scheme_directory()
        file_path = Path("./Scheme") / filename
        
        with open(file_path, 'w', encoding='utf-8') as file:
            file.write(content)
        
        print(f"Generated: {file_path}")
    
    def generate_const_classes(self) -> None:
        """Main method to generate all const classes"""
        print(f"Reading scheme file: {self.scheme_file_path}")
        
        # Read and parse the scheme file
        content = self.read_scheme_file()
        self.extract_column_names(content)
        self.extract_table_names(content)
        
        print(f"Found {len(self.column_names)} unique column names")
        print(f"Found {len(self.table_names)} unique table names")
        
        # Generate and write const classes
        column_const_class = self.generate_column_const_class()
        table_const_class = self.generate_table_const_class()
        
        self.write_const_file("ColumnName.php", column_const_class)
        self.write_const_file("TableName.php", table_const_class)
        
        print("Const class generation completed successfully!")
        
        # Print summary
        print("\nColumn Names Found:")
        for col in sorted(self.column_names):
            print(f"  - {col}")
            
        print("\nTable Names Found:")
        for table in sorted(self.table_names):
            print(f"  - {table}")


def main():
    """Main entry point"""
    try:
        generator = PhpConstGenerator()
        generator.generate_const_classes()
    except Exception as e:
        print(f"Error: {e}")
        return 1
    
    return 0


if __name__ == "__main__":
    exit(main())
