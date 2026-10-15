<?php
// ========================================================
// ShirtCraft — settings
// Defaults match a fresh XAMPP install (MySQL user "root", no password).
// ========================================================
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;          // change to 3307 etc. if XAMPP MySQL uses another port
const DB_NAME = 'stallion_db';
const DB_USER = 'root';
const DB_PASS = '';

// Shown on checkout / receipt for bank-transfer orders. EDIT THESE.
const BANK_NAME    = 'First Bank of Nigeria';
const BANK_ACCOUNT = '3012345678';
const BANK_HOLDER  = 'STALLION CUSTOM APPARELS LTD';

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;   // 10 MB artwork limit
