# Database Schema - SQL Table Structure

## Table: `members`
```
+---------------+--------------+------+-----+---------------------+----------------+
| Field         | Type         | Null | Key | Default             | Extra          |
+---------------+--------------+------+-----+---------------------+----------------+
| id            | int          | NO   | PRI | NULL                | auto_increment |
| name          | varchar(255) | NO   |     | NULL                |                |
| member_number | varchar(50)  | YES  | UNI | NULL                |                |
| phone         | varchar(20)  | YES  |     | NULL                |                |
| email         | varchar(100) | YES  |     | NULL                |                |
| created_at    | timestamp    | YES  |     | CURRENT_TIMESTAMP   |                |
+---------------+--------------+------+-----+---------------------+----------------+
```

## Table: `member_accounts`
```
+-------------------------+---------------+------+-----+---------------------+----------------+
| Field                   | Type          | Null | Key | Default             | Extra          |
+-------------------------+---------------+------+-----+---------------------+----------------+
| id                      | int           | NO   | PRI | NULL                | auto_increment |
| member_id               | int           | NO   | MUL | NULL                |                |
| savings_opening_balance | decimal(15,2) | YES  |     | 0.00                |                |
| savings_current_balance | decimal(15,2) | YES  |     | 0.00                |                |
| savings_account_opened  | date          | YES  |     | NULL                |                |
| loan_opening_balance    | decimal(15,2) | YES  |     | 0.00                |                |
| loan_current_balance    | decimal(15,2) | YES  |     | 0.00                |                |
| loan_account_opened     | date          | YES  |     | NULL                |                |
| seasonal_tickets_opening_balance  | decimal(15,2) | YES  |     | 0.00                |                |
| seasonal_tickets_current_balance  | decimal(15,2) | YES  |     | 0.00                |                |
| seasonal_tickets_account_opened   | date          | YES  |     | NULL                |                |
| operations_opening_balance  | decimal(15,2) | YES  |     | 0.00                |                |
| operations_current_balance  | decimal(15,2) | YES  |     | 0.00                |                |
| operations_account_opened   | date          | YES  |     | NULL                |                |
| created_at              | timestamp     | YES  |     | CURRENT_TIMESTAMP   |                |
| updated_at              | timestamp     | YES  |     | CURRENT_TIMESTAMP   | on update      |
+-------------------------+---------------+------+-----+---------------------+----------------+
```

## Table: `transactions`
```
+-------------------+--------------------------------------------------------+------+-----+---------------------+----------------+
| Field             | Type                                                   | Null | Key | Default             | Extra          |
+-------------------+--------------------------------------------------------+------+-----+---------------------+----------------+
| id                | int                                                    | NO   | PRI | NULL                | auto_increment |
| member_id         | int                                                    | NO   | MUL | NULL                |                |
| account_type_id   | int                                                    | NO   |     | NULL                |                |
| amount            | decimal(15,2)                                          | NO   |     | NULL                |                |
| transaction_type  | enum('deposit','withdrawal','transfer',                | NO   |     | NULL                |                |
|                   |      'fee','interest',                                 |      |     |                     |                |
|                   |      )                                                 |      |     |                     |                |
| balance_before    | decimal(15,2)                                          | NO   |     | NULL                |                |
| balance_after     | decimal(15,2)                                          | NO   |     | NULL                |                |
| description       | text                                                   | YES  |     | NULL                |                |
| reference_number  | varchar(100)                                           | YES  |     | NULL                |                |
| created_at        | timestamp                                              | YES  |     | CURRENT_TIMESTAMP   |                |
| transaction_date  | date                                                   | NO   |     | NULL                |                |
+-------------------+--------------------------------------------------------+------+-----+---------------------+----------------+
```

## Indexes
```
Table: members
+-------------+------------+---------------+--------------+
| Table       | Non_unique | Key_name      | Column_name  |
+-------------+------------+---------------+--------------+
| members     |          0 | PRIMARY       | id           |
| members     |          0 | member_number | member_number|
+-------------+------------+---------------+--------------+

Table: member_accounts
+----------------+------------+-----------+-------------+
| Table          | Non_unique | Key_name  | Column_name |
+----------------+------------+-----------+-------------+
| member_accounts|          0 | PRIMARY   | id          |
| member_accounts|          1 | member_id | member_id   |
+----------------+------------+-----------+-------------+

Table: transactions
+-------------+------------+------------------------+------------------+
| Table       | Non_unique | Key_name               | Column_name      |
+-------------+------------+------------------------+------------------+
| transactions|          0 | PRIMARY                | id               |
| transactions|          1 | member_id              | member_id        |
| transactions|          1 | member_account_date    | member_id        |
| transactions|          1 | member_account_date    | account_type     |
| transactions|          1 | member_account_date    | transaction_date |
| transactions|          1 | transaction_date       | transaction_date |
+-------------+------------+------------------------+------------------+
```

## Foreign Key Constraints
```
Table: member_accounts
+----------------+------------------+-------------------+
| Constraint     | Column           | Referenced Table  |
+----------------+------------------+-------------------+
| FK_member_acct | member_id        | members(id)       |
+----------------+------------------+-------------------+

Table: transactions
+----------------+------------------+-------------------+
| Constraint     | Column           | Referenced Table  |
+----------------+------------------+-------------------+
| FK_trans_member| member_id        | members(id)       |
+----------------+------------------+-------------------+
```

## Sample Data Preview

### members
```
+----+-----------+---------------+----------------+-------------------+---------------------+
| id | name      | member_number | phone          | email             | created_at          |
+----+-----------+---------------+----------------+-------------------+---------------------+
|  1 | John Doe  | MEM001        | +254712345678  | john@example.com  | 2025-07-01 10:00:00 |
|  2 | Jane Smith| MEM002        | +254798765432  | jane@example.com  | 2025-07-02 11:30:00 |
+----+-----------+---------------+----------------+-------------------+---------------------+
```

### member_accounts
```
+----+-----------+-------------------------+-------------------------+-----------------------+---------------------+---------------------+-------------------+------------------------+------------------------+----------------------+---------------------+---------------------+
| id | member_id | savings_opening_balance | savings_current_balance | savings_account_opened| loan_opening_balance| loan_current_balance| loan_account_opened| shares_opening_balance | shares_current_balance | shares_account_opened| created_at          | updated_at          |
+----+-----------+-------------------------+-------------------------+-----------------------+---------------------+---------------------+-------------------+------------------------+------------------------+----------------------+---------------------+---------------------+
|  1 |         1 |                50000.00 |                65000.00 | 2025-07-01            |            200000.00 |            195000.00| 2025-07-01        |               25000.00 |               25000.00 | 2025-07-01           | 2025-07-01 10:00:00 | 2025-07-28 09:15:00 |
|  2 |         2 |                30000.00 |                45000.00 | 2025-07-02            |                 0.00 |                0.00| NULL              |               10000.00 |               15000.00 | 2025-07-02           | 2025-07-02 11:30:00 | 2025-07-28 14:20:00 |
+----+-----------+-------------------------+-------------------------+-----------------------+---------------------+---------------------+-------------------+------------------------+------------------------+----------------------+---------------------+---------------------+
```

### transactions
```
+----+-----------+--------------+----------+------------------+----------------+---------------+-----------------------------+------------------+---------------------+------------------+
| id | member_id | account_type | amount   | transaction_type | balance_before | balance_after | description                 | reference_number | created_at          | transaction_date |
+----+-----------+--------------+----------+------------------+----------------+---------------+-----------------------------+------------------+---------------------+------------------+
|  1 |         1 | savings      | 50000.00 | opening_balance  |           0.00 |      50000.00 | Account opening balance     | NULL             | 2025-07-01 10:00:00 | 2025-07-01       |
|  2 |         1 | loan         |200000.00 | opening_balance  |           0.00 |     200000.00 | Loan account opening        | NULL             | 2025-07-01 10:00:00 | 2025-07-01       |
|  3 |         1 | shares       | 25000.00 | opening_balance  |           0.00 |      25000.00 | Shares account opening      | NULL             | 2025-07-01 10:00:00 | 2025-07-01       |
|  4 |         1 | savings      | 15000.00 | deposit          |    50000.00    |      65000.00 | Monthly savings deposit     | DEP001           | 2025-07-15 09:30:00 | 2025-07-15       |
|  5 |         1 | loan         | -5000.00 | loan_payment     |   200000.00    |     195000.00 | Monthly loan payment        | LP001            | 2025-07-20 14:45:00 | 2025-07-20       |
+----+-----------+--------------+----------+------------------+----------------+---------------+-----------------------------+------------------+---------------------+------------------+
```

## Key Information:

**PRI** = Primary Key  
**MUL** = Multiple (Index allows duplicates)  
**UNI** = Unique Key  
**auto_increment** = Auto-incrementing field  
**on update** = Updates automatically when record is modified

**Data Types:**
- `int` = Integer numbers
- `varchar(n)` = Variable character string, max n characters  
- `decimal(15,2)` = Decimal number, 15 digits total, 2 after decimal point
- `enum()` = Enumerated list of allowed values
- `text` = Large text field
- `date` = Date only (YYYY-MM-DD)
- `timestamp` = Date and time (YYYY-MM-DD HH:MM:SS)