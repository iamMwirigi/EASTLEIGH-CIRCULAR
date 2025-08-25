# iGuru Collections Management System

## Overview
A comprehensive collections management system for vehicle owners with integrated SMS notifications, member account management, and transaction tracking.

## System Architecture

### Core Components
- **Collections Management**: Vehicle-based collection tracking
- **Member Accounts**: Individual account balances for different deduction types
- **Transaction System**: CRUD operations for financial transactions
- **SMS Integration**: Real-time notifications to vehicle owners
- **Dashboard**: Admin and user dashboards with summaries

## Database Schema

### Key Tables
- `member`: Member information (name, phone, number)
- `vehicle`: Vehicle details linked to owners
- `new_transaction`: Collections/transactions data
- `member_accounts`: Account balances for each member
- `member_account_types`: Links members to account types
- `account_type`: Account type definitions (Loans, Savings, etc.)
- `transactions`: Detailed transaction records
- `organization_details`: Company information for SMS

## Collections Logic

### Collection Creation Flow
1. **Input Validation**
   - Required: `number_plate`, `t_time`, `t_date`
   - Optional: `for_date`, `stage_id`
   - Deductions: `operations`, `loans`, `county`, `savings`, `insurance`
   - Validation: No negative values, at least one non-zero deduction

2. **Receipt Generation**
   - Auto-generates sequential receipt numbers (IG-1, IG-2, ...)
   - Format: `IG-{number}`

3. **Amount Calculation**
   - Automatically calculates total from all deductions
   - Formula: `amount = operations + loans + county + savings + insurance`
   - Never null (0 if no deductions)

4. **Member Account Updates**
   - Finds vehicle owner from `vehicle` table
   - Auto-creates `member_accounts` record if not exists
   - Updates balances:
     ```sql
     savings_current_balance += savings_deduction
     loan_current_balance += loans_deduction
     county_current_balance += county_deduction
     insurance_current_balance += insurance_deduction
     ```

5. **SMS Notification**
   - Only sends if deductions > 0 (excludes operations)
   - Format:
     ```
     iGuru

     KFA 123A
     Loans: Ksh 2000
     County: Ksh 1000
     Savings: Ksh 1500
     Insurance: Ksh 500
     Total: Ksh 5000
     Date: [29-07-2025 21:13]

     Thank you
     ```

### Collection Listing
- **Filtering**: Excludes collections with all zero deductions
- **Sorting**: By `t_date DESC, t_time DESC, id DESC`
- **Summary**: Calculates totals for filtered data only
- **Pagination**: Optional `limit` parameter
- **Response**: Includes company contacts and name

### Collection Updates
- **Balance Reversion**: Reverts old collection's effect on member accounts
- **Balance Application**: Applies new collection's effect on member accounts
- **Validation**: Prevents negative deduction values
- **SMS**: Sends updated notification with new data

## Member Accounts Logic

### Account Types
1. **Loans** (ID: 1): Loan balances and payments
2. **Savings** (ID: 2): Savings account balances
3. **Operations** (ID: 4): Operational deductions
4. **Insurance** (ID: 6): Insurance contributions
5. **County** (ID: 7): county fees collection

### Balance Management
- **Auto-Creation**: Member accounts created automatically when needed
- **Opening Balances**: Tracked separately from current balances
- **Real-Time Updates**: Balances updated with each transaction/collection
- **Validation**: Prevents negative balances for non-loan accounts

### Member Account Linking
- `member_account_types`: Links members to account types
- Multiple account types per member
- Automatic initialization during member creation

## Transaction System

### Transaction Types
1. **deposit**: Adds to account balance
2. **withdrawal**: Subtracts from account balance
3. **transfer**: Moves between accounts (dedicated endpoint)
4. **interest**: Adds interest to account
5. **fee**: Subtracts fees from account

### Special Logic

#### Loan Payments (Withdrawal from Loan Account)
```php
if (account_type_id == 1 && transaction_type == 'withdrawal') {
    if (loan_balance <= 0) {
        // Entire payment goes to savings
        balance_after = 0;
        savings_balance += payment_amount;
    } else if (payment_amount <= loan_balance) {
        // Reduces loan balance
        balance_after = loan_balance - payment_amount;
    } else {
        // Excess goes to savings
        balance_after = 0;
        excess = payment_amount - loan_balance;
        savings_balance += excess;
    }
}
```

#### Transfer Logic
- **Validation**: Checks sufficient funds in source account
- **Dual Update**: Subtracts from source, adds to destination
- **Transaction Record**: Records as 'transfer' type
- **Balance Tracking**: Updates both account balances

#### Withdrawal Validation
```php
if (account_type_id != 1 && amount > balance_before) {
    // Reject withdrawal for non-loan accounts
    return "Insufficient funds for withdrawal";
}
```

### Transaction Flow
1. **Validation**: Required fields, account existence
2. **Balance Check**: Current balance retrieval
3. **Calculation**: Balance before/after based on transaction type
4. **Update**: Member account balance update
5. **Record**: Transaction record creation
6. **Response**: Transaction details with member/account info

## API Endpoints

### Collections

#### Create Collection
**Endpoint:** `POST /api/v1/collections/create`

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Payload:**
```json
{
  "number_plate": "KFA 123A",
  "t_time": "15:00",
  "t_date": "2025-07-22",
  "for_date": "2025-07-22",
  "loans": 2000,
  "savings": 1500,
  "county": 1000,
  "insurance": 500,
  "operations": 300,
  "stage_id": 1
}
```

**Response:**
```json
{
  "message": "Collection created successfully",
  "response": "success",
  "collection": {
    "id": 198460,
    "number_plate": "KFA 123A",
    "savings": "1500",
    "insurance": "500",
    "t_time": "15:00",
    "t_date": "2025-07-22",
    "s_time": "21:11:55",
    "s_date": "2025-07-29",
    "client_side_id": "CLNT-68890eeb795b1",
    "receipt_no": "IG-225",
    "collected_by": "admin",
    "stage_name": "Thika",
    "delete_status": 0,
    "for_date": "2025-07-22",
    "amount": "5000.00",
    "loans": 2000,
    "county": 1000,
    "total": 5000,
    "company_name": "iGuru",
    "company_contacts": "Management-0729690274,Komarock-0000000000, Eastleigh-0000000000"
  }
}
```

#### List Collections
**Endpoint:** `GET /api/v1/collections/read`

**Query Parameters:**
```
?start_date=2025-07-01&end_date=2025-07-31&limit=50
```

**Response:**
```json
{
  "message": "Collections retrieved successfully",
  "response": "success",
  "data": [
    {
      "id": 198460,
      "number_plate": "KFA 123A",
      "savings": "1500",
      "insurance": "500",
      "t_time": "15:00",
      "t_date": "2025-07-22",
      "amount": "5000.00",
      "loans": 2000,
      "county": 1000,
      "company_name": "iGuru",
      "company_contacts": "Management-0729690274,Komarock-0000000000, Eastleigh-0000000000"
    }
  ],
  "summary": {
    "total_operations": 300,
    "total_loans": 2000,
    "total_county": 1000,
    "total_savings": 1500,
    "total_insurance": 500,
    "total_amount": 5000,
    "transactions": 1
  }
}
```

#### Update Collection
**Endpoint:** `PUT /api/v1/collections/update`

**Payload:**
```json
{
  "id": 198460,
  "number_plate": "KFA 123A",
  "t_time": "15:00",
  "t_date": "2025-07-22",
  "loans": 2500,
  "savings": 2000,
  "county": 1200,
  "insurance": 600
}
```

**Response:**
```json
{
  "message": "Collection updated successfully",
  "response": "success",
  "collection": {
    "id": 198460,
    "number_plate": "KFA 123A",
    "amount": "6300.00",
    "loans": 2500,
    "savings": 2000,
    "county": 1200,
    "insurance": 600
  }
}
```

### Members

#### Create Member
**Endpoint:** `POST /api/v1/members/create`

**Payload:**
```json
{
  "name": "John Doe",
  "phone_number": "0712345678",
  "number": 12345,
  "accounts": [1, 2, 7]
}
```

**Response:**
```json
{
  "message": "Member created successfully",
  "response": "success",
  "data": {
    "id": 312,
    "name": "John Doe",
    "phone_number": "0712345678",
    "number": 12345,
    "accounts": [
      {
        "id": 42,
        "member_id": 312,
        "account_type": {
          "id": 1,
          "name": "Loans",
          "description": "Loans account for members with loans"
        }
      }
    ],
    "balances": {
      "savings_current_balance": 0,
      "loan_current_balance": 0,
      "county_current_balance": 0,
      "insurance_current_balance": 0,
      "operations_current_balance": 0
    }
  }
}
```

#### Get Member
**Endpoint:** `GET /api/v1/members/read_one?id=311`

**Response:**
```json
{
  "message": "Member found",
  "response": "success",
  "data": {
    "id": 311,
    "name": "test",
    "phone_number": "0714593953",
    "number": 1,
    "accounts": [
      {
        "id": 37,
        "member_id": 311,
        "account_type": {
          "id": 1,
          "name": "Loans",
          "description": "Loans account for members with loans"
        }
      }
    ],
    "balances": {
      "savings_current_balance": 769900,
      "loan_current_balance": -3700,
      "county_current_balance": 0,
      "insurance_current_balance": 0,
      "operations_current_balance": 0
    }
  }
}
```

### Transactions

#### Create Transaction
**Endpoint:** `POST /api/v1/transactions/create`

**Payload:**
```json
{
  "member_id": 311,
  "account_type_id": 2,
  "amount": 10000,
  "transaction_type": "deposit",
  "description": "Monthly savings deposit",
  "transaction_date": "2025-07-22"
}
```

**Response:**
```json
{
  "message": "Transaction created successfully",
  "response": "success",
  "data": {
    "id": 4,
    "member_id": 311,
    "account_type_id": 2,
    "destination_account_type_id": null,
    "amount": 10000,
    "transaction_type": "deposit",
    "balance_before": 769900,
    "balance_after": 779900,
    "description": "Monthly savings deposit",
    "transaction_date": "2025-07-22",
    "member_name": "test",
    "member_acc_type_name": "Savings"
  }
}
```

#### Transfer Between Accounts
**Endpoint:** `POST /api/v1/transactions/transfer`

**Payload:**
```json
{
  "member_id": 311,
  "account_type_id": 2,
  "destination_account_type_id": 1,
  "amount": 5000,
  "description": "Transfer from savings to loan"
}
```

**Response:**
```json
{
  "message": "Transfer completed successfully",
  "response": "success",
  "data": {
    "id": 5,
    "member_id": 311,
    "account_type_id": 2,
    "destination_account_type_id": 1,
    "amount": 5000,
    "transaction_type": "transfer",
    "balance_before": 779900,
    "balance_after": 774900,
    "description": "Transfer from savings to loan",
    "transaction_date": "2025-07-22",
    "member_name": "test",
    "member_acc_type_name": "Savings"
  }
}
```

### Account Types

#### Create Account Type
**Endpoint:** `POST /api/v1/account_types/create`

**Payload:**
```json
{
  "name": "Emergency Fund",
  "description": "Emergency savings account"
}
```

**Response:**
```json
{
  "message": "Account type created successfully",
  "response": "success",
  "data": {
    "id": 6,
    "name": "Emergency Fund",
    "description": "Emergency savings account"
  }
}
```

### Dashboard

#### Admin Dashboard
**Endpoint:** `GET /api/v1/dashboard/admin?start_date=2025-07-01&end_date=2025-07-31`

**Response:**
```json
{
  "message": "Dashboard data retrieved successfully",
  "response": "success",
  "data": {
    "stages_transactions": [
      {
        "stage_name": "Thika",
        "collected_by": "admin",
        "operations": 1500,
        "loans": 8000,
        "seasonal_tickets": 6000,
        "savings": 12000,
        "insurance": 3000,
        "stage_total": 30500
      }
    ],
    "totals_summary": {
      "grand_total_deductions": 30500,
      "total_stages": 1
    }
  }
}
```

#### User Dashboard
**Endpoint:** `GET /api/v1/dashboard/user?start_date=2025-07-01&end_date=2025-07-31`

**Response:**
```json
{
  "message": "User dashboard data retrieved successfully",
  "response": "success",
  "data": {
    "user_id": 2,
    "username": "admin",
    "total_amount": 15000,
    "total_transactions": 5,
    "date_range": {
      "start_date": "2025-07-01",
      "end_date": "2025-07-31"
    }
  }
}
```

### Error Responses

#### Validation Error
```json
{
  "message": "Insufficient funds for withdrawal. Available balance: 1000, Requested amount: 5000",
  "response": "error"
}
```

#### Not Found Error
```json
{
  "message": "Member not found",
  "response": "error"
}
```

#### Authorization Error
```json
{
  "message": "Authorization header not found",
  "response": "error"
}
```

#### Server Error
```json
{
  "message": "Failed to create collection",
  "response": "error"
}
```

## SMS Integration

### AfricasTalking SDK
- **Username**: mzigosms
- **API Key**: Configured in environment
- **From**: iGuru
- **Format**: Structured message with deductions and totals

### SMS Triggers
- **Collections**: Automatic for vehicle owners
- **Conditions**: Only if deductions > 0 (excludes operations)
- **Real-Time**: Uses latest collection data from database

### SMS Content
- Vehicle plate number
- Non-zero deductions with "Ksh" prefix
- "Tickets" label for seasonal_tickets
- Total amount
- Company name and timestamp

## Data Flow Examples

### Collection Creation
```
Input: {
  "number_plate": "KFA 123A",
  "t_time": "15:00",
  "t_date": "2025-07-22",
  "loans": 2000,
  "savings": 1500,
  "seasonal_tickets": 1000,
  "insurance": 500
}

Process:
1. Validate input
2. Calculate total: 5000
3. Insert collection record
4. Find vehicle owner (member 311)
5. Update member accounts:
   - loan_current_balance += 2000
   - savings_current_balance += 1500
   - seasonal_tickets_current_balance += 1000
   - insurance_current_balance += 500
6. Send SMS to member phone
7. Return collection data
```

### Transaction Withdrawal
```
Input: {
  "member_id": 311,
  "account_type_id": 2,
  "amount": 1000,
  "transaction_type": "withdrawal"
}

Process:
1. Validate sufficient funds
2. Calculate balance_after = balance_before - amount
3. Update member_accounts.savings_current_balance
4. Insert transaction record
5. Return transaction data
```

### Transfer Between Accounts
```
Input: {
  "member_id": 311,
  "account_type_id": 2,
  "destination_account_type_id": 1,
  "amount": 5000
}

Process:
1. Validate sufficient funds in source
2. Subtract from source account
3. Add to destination account
4. Insert transaction record
5. Return transfer data
```

## Security & Validation

### Input Validation
- Required field checking
- Data type validation
- Negative value prevention
- Sufficient funds validation

### Authorization
- JWT-based authentication
- Role-based access control
- Admin, user, member roles

### Data Integrity
- Transaction rollback on errors
- Balance consistency checks
- Unique constraint enforcement

## Timezone Handling
- **Server Timezone**: Africa/Nairobi
- **Date Format**: Y-m-d
- **Time Format**: H:i:s
- **SMS Timestamp**: d-m-Y H:i

## Error Handling
- **HTTP Status Codes**: Appropriate error responses
- **Validation Errors**: Clear error messages
- **Database Errors**: Graceful error handling
- **SMS Failures**: Logged but don't break collection creation

## Performance Considerations
- **Prepared Statements**: SQL injection prevention
- **Indexed Queries**: Optimized database access
- **Batch Operations**: Efficient bulk updates
- **Connection Pooling**: Database connection management

## Monitoring & Logging
- **SMS Logging**: Successful/failed SMS attempts
- **Transaction Logging**: All financial transactions
- **Error Logging**: System errors and exceptions
- **Audit Trail**: Complete transaction history

This system provides a robust, real-time collections management solution with integrated SMS notifications and comprehensive account tracking. 