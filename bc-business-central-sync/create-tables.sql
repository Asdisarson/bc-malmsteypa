-- BC Business Central Sync - Database Tables Creation Script
-- Run this script directly in your MySQL database to create the required tables
-- Replace 'wp_' with your actual table prefix if different

-- Drop existing tables if they exist (in correct order for foreign key constraints)
DROP TABLE IF EXISTS wp_bc_dokobit_user_phones;
DROP TABLE IF EXISTS wp_bc_company_pricelists;
DROP TABLE IF EXISTS wp_bc_pricelist_lines;
DROP TABLE IF EXISTS wp_bc_pricelists;
DROP TABLE IF EXISTS wp_bc_dokobit_companies;

-- Create companies table
CREATE TABLE wp_bc_dokobit_companies (
    id int(11) NOT NULL AUTO_INCREMENT,
    bc_company_id varchar(191) DEFAULT NULL,
    company_name varchar(255) NOT NULL,
    company_number varchar(100) DEFAULT NULL,
    address text DEFAULT NULL,
    city varchar(100) DEFAULT NULL,
    postal_code varchar(20) DEFAULT NULL,
    country varchar(100) DEFAULT NULL,
    phone varchar(50) DEFAULT NULL,
    email varchar(191) DEFAULT NULL,
    last_sync datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY bc_company_id (bc_company_id),
    UNIQUE KEY company_number (company_number),
    KEY company_name (company_name),
    KEY last_sync (last_sync)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Create pricelists table
CREATE TABLE wp_bc_pricelists (
    id int(11) NOT NULL AUTO_INCREMENT,
    bc_pricelist_id varchar(191) NOT NULL,
    code varchar(100) NOT NULL,
    name varchar(255) NOT NULL,
    currency_code varchar(10) DEFAULT 'USD',
    last_modified datetime DEFAULT NULL,
    last_sync datetime DEFAULT CURRENT_TIMESTAMP,
    last_kept datetime DEFAULT NULL,
    last_overwritten datetime DEFAULT NULL,
    last_manual_edit datetime DEFAULT NULL,
    status varchar(50) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY bc_pricelist_id (bc_pricelist_id),
    UNIQUE KEY code (code),
    KEY status (status),
    KEY last_sync (last_sync)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Create company pricelist assignments table
CREATE TABLE wp_bc_company_pricelists (
    id int(11) NOT NULL AUTO_INCREMENT,
    company_id int(11) NOT NULL,
    pricelist_id int(11) NOT NULL,
    assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
    last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY company_pricelist (company_id, pricelist_id),
    KEY company_id (company_id),
    KEY pricelist_id (pricelist_id),
    FOREIGN KEY (company_id) REFERENCES wp_bc_dokobit_companies(id) ON DELETE CASCADE,
    FOREIGN KEY (pricelist_id) REFERENCES wp_bc_pricelists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Create pricelist lines table
CREATE TABLE wp_bc_pricelist_lines (
    id int(11) NOT NULL AUTO_INCREMENT,
    bc_line_id varchar(191) NOT NULL,
    pricelist_id int(11) NOT NULL,
    item_id bigint(20) DEFAULT NULL,
    bc_item_id varchar(191) DEFAULT NULL,
    item_number varchar(100) NOT NULL,
    unit_price decimal(10,2) NOT NULL,
    currency_code varchar(10) DEFAULT 'USD',
    starting_date datetime DEFAULT NULL,
    ending_date datetime DEFAULT NULL,
    minimum_quantity int(11) DEFAULT 1,
    last_sync datetime DEFAULT CURRENT_TIMESTAMP,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY bc_line_id (bc_line_id),
    KEY pricelist_id (pricelist_id),
    KEY item_id (item_id),
    KEY item_number (item_number),
    KEY unit_price (unit_price),
    FOREIGN KEY (pricelist_id) REFERENCES wp_bc_pricelists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Create user phones table
CREATE TABLE wp_bc_dokobit_user_phones (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    phone_number varchar(50) NOT NULL,
    personal_code varchar(20) DEFAULT NULL,
    company_id int(11) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY phone_number (phone_number),
    KEY user_id (user_id),
    KEY company_id (company_id),
    KEY personal_code (personal_code),
    FOREIGN KEY (company_id) REFERENCES wp_bc_dokobit_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Insert WordPress option for database version
INSERT INTO wp_options (option_name, option_value, autoload) 
VALUES ('bc_business_central_sync_db_version', '1.1.0', 'no')
ON DUPLICATE KEY UPDATE option_value = '1.1.0';

-- Success message
SELECT 'BC Business Central Sync tables created successfully!' as status;
