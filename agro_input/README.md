intial# Agro Input Dealers — Online Farmers E-Commerce Website

A PHP + MySQL starter website based on the business plan for **Online Farmers E-Commerce and E-Business Management System — Agro Input Dealers, Fort Portal, Uganda**.

## Features
- Product catalogue for improved seeds, fertilizers, pesticides, animal feeds, veterinary supplies and farm tools
- Search and category filtering
- Customer registration and login
- Shopping cart and checkout
- Order creation with UGX totals
- Payment status tracking (manual confirmation / cash on delivery placeholder)
- Customer order history
- Admin dashboard to add products, update stock, and update order/payment statuses
- Prepared SQL statements, password hashing, CSRF tokens, and role checks

## Requirements
- Windows, macOS or Linux
- XAMPP (Apache + PHP + MySQL/MariaDB) or equivalent
- PHP 8.1+ recommended

## Install with XAMPP (Windows)
1. Install and open XAMPP.
2. Start **Apache** and **MySQL**.
3. Copy the `agro_input_website` folder into `C:\xampp\htdocs\`.
4. Open phpMyAdmin at `http://localhost/phpmyadmin`.
5. Import `database.sql` into phpMyAdmin.
6. Open `config.php` and confirm the database settings (default XAMPP settings: host `localhost`, user `root`, blank password, database `agro_input_db`).
7. Visit `http://localhost/agro_input_website/`.

## Create the first admin account
1. Register a normal account through the website.
2. In phpMyAdmin, open the `agro_input_db` database and run:
   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
   ```
3. Log out and log in again. The Admin link will appear.

## Important notes before real-world deployment
- This is a functional academic/demo starter, not a production-ready payment gateway.
- Payments are recorded as **pending** until an administrator confirms them. No card or mobile-money credentials are collected.
- To accept real Mobile Money payments, integrate an approved provider and verify payment callbacks server-side.
- To send customer SMS notifications, enable the PHP cURL extension and configure these variables in Apache's `httpd.conf` (remove `AT_SENDER_ID` if you do not have an approved sender ID):
   ```apache
   SetEnv AT_USERNAME "your_africastalking_username"
   SetEnv AT_API_KEY "your_api_key"
   SetEnv AT_SENDER_ID "your_approved_sender_id"
   ```
   Restart Apache after editing the file. Create an Africa's Talking account and use its sandbox credentials for testing; sandbox messages are not delivered to real phones. Use production credentials and an approved sender ID for live delivery. Ugandan local phone numbers are converted to `+256` format automatically. SMS failures are written to the PHP error log and do not cancel orders. Never commit your API key to the project.
- Change database credentials, enable HTTPS, configure backups, add rate limiting, and review Ugandan business, consumer-protection, tax and data-protection requirements before commercial launch.
- Replace sample product details/prices with verified business data. Sample inventory is for demonstration only.
