# ERD - AJM Bengkel Management System

## Database Tables

### 1. users
Primary admin/staff accounts.
```
id: bigint PK
name: varchar(255)
email: varchar(255) UNIQUE
password: varchar(255)
role: enum('admin', 'mechanic', 'cashier')
created_at, updated_at: timestamp
```

### 2. customers
```
id: bigint PK
name: varchar(255)
phone: varchar(20)
address: text NULL
created_at, updated_at: timestamp
```

### 3. vehicles
```
id: bigint PK
customer_id: bigint FK → customers.id
license_plate: varchar(20) UNIQUE
brand: varchar(100)
model: varchar(100)
year: year
created_at, updated_at: timestamp
```

### 4. spareparts
```
id: bigint PK
name: varchar(255)
sku: varchar(100) UNIQUE
category: varchar(100)
stock: int DEFAULT 0
min_stock: int DEFAULT 5
unit_price: decimal(10,2)
created_at, updated_at: timestamp
```

### 5. work_orders
```
id: bigint PK
vehicle_id: bigint FK → vehicles.id
mechanic_id: bigint FK → users.id
status: enum('pending', 'in_progress', 'completed', 'paid')
description: text
total_cost: decimal(10,2) DEFAULT 0
started_at: timestamp NULL
completed_at: timestamp NULL
created_at, updated_at: timestamp
```

### 6. work_order_items
Service items/tasks within a work order.
```
id: bigint PK
work_order_id: bigint FK → work_orders.id
sparepart_id: bigint FK → spareparts.id NULL
service_name: varchar(255)
quantity: int DEFAULT 1
unit_price: decimal(10,2)
subtotal: decimal(10,2)
created_at, updated_at: timestamp
```

### 7. service_photos
Photo documentation for specific work order items.
```
id: bigint PK
work_order_item_id: bigint FK → work_order_items.id
photo_path: varchar(500)
description: text NULL
uploaded_at: timestamp
created_at, updated_at: timestamp
```

### 8. transactions (Optional Financial Module)
```
id: bigint PK
work_order_id: bigint FK → work_orders.id NULL
type: enum('income', 'expense')
amount: decimal(10,2)
description: text
transaction_date: date
created_at, updated_at: timestamp
```

### 9. audit_logs (Commit/Rollback Feature)
```
id: bigint PK
user_id: bigint FK → users.id
table_name: varchar(100)
row_id: bigint
action: enum('create', 'update', 'delete')
old_values: json NULL
new_values: json NULL
created_at: timestamp
```

## Relationships

- customers → vehicles (1:N)
- vehicles → work_orders (1:N)
- users → work_orders (1:N) [mechanic assignment]
- work_orders → work_order_items (1:N)
- spareparts → work_order_items (1:N)
- work_order_items → service_photos (1:N)
- work_orders → transactions (1:1 or 1:N)

## Triggers/Procedures (Planned)

1. **Trigger: auto_deduct_stock**
   - On work_order status change to 'completed'
   - Deduct spareparts.stock by work_order_items.quantity

2. **Procedure: generate_monthly_report**
   - Aggregate transactions by month
   - Return summary: total_income, total_expense, profit

3. **Function: calculate_work_order_total**
   - Sum work_order_items.subtotal for a given work_order_id
