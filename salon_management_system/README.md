# Salon Management System

A comprehensive web-based salon management system built with PHP, MySQL, HTML, CSS, and JavaScript. Features role-based access control, point of sales, inventory management, staff tracking, and sales reporting.

## 📋 Features

### 🔐 Authentication & Access Control
- Secure login system with password hashing
- Role-based access (Admin & Staff)
- Feature-based permissions for staff
- Session management
- Account activation/deactivation

### 👨‍💼 Admin Features
- **Dashboard**: Real-time overview of sales, customers, and staff
- **Point of Sales (POS)**: Process product and service transactions
- **Sales Reports**: Filter and view sales by date range (detailed/summary)
- **Inventory Management**: Full CRUD operations for products
- **Staff Duty Tracking**: Manual performance recording
- **Staff Management**: Create accounts, assign permissions, manage access

### 👩‍🔧 Staff Features
- Limited dashboard based on assigned permissions
- Optional access to:
  - Point of Sales
  - Inventory (view-only)
  - Schedules
  - Personal reports

## 🛠️ Technical Stack

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Server**: Apache (XAMPP)
- **Tools**: phpMyAdmin

## 📦 Installation Instructions

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser (Chrome, Firefox, Edge)

### Step 1: Install XAMPP
1. Download XAMPP from https://www.apachefriends.org/
2. Install XAMPP in `C:\xampp` (Windows) or `/opt/lampp` (Linux)
3. Start Apache and MySQL from XAMPP Control Panel

### Step 2: Setup Project Files
1. Copy the `salon_management_system` folder to:
   - Windows: `C:\xampp\htdocs\`
   - Linux: `/opt/lampp/htdocs/`

2. Your folder structure should look like:
   ```
   htdocs/
   └── salon_management_system/
       ├── admin/
       ├── staff/
       ├── config/
       ├── assets/
       ├── index.php
       └── database.sql
   ```

### Step 3: Create Database
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click on "Import" tab
3. Choose file: `salon_management_system/database.sql`
4. Click "Go" to import
5. Database `salon_management` will be created with all tables and default data

**OR** manually create:
1. Click "New" to create database
2. Name it: `salon_management`
3. Click on SQL tab
4. Copy-paste contents of `database.sql`
5. Click "Go"

### Step 4: Access the System
1. Open your browser
2. Navigate to: http://localhost/salon_management_system
3. Login with default admin credentials:
   - **Username**: `admin`
   - **Password**: `admin`

## 🔑 Default Login Credentials

### Admin Account
- **Username**: `admin`
- **Password**: `admin`
- **Access**: Full system access

### Creating Staff Accounts
1. Login as admin
2. Go to "Staff Management"
3. Fill in staff details and assign permissions
4. Staff can login with created credentials

## 📁 Folder Structure

```
salon_management_system/
│
├── config/
│   ├── database.php          # Database connection
│   └── auth.php               # Authentication functions
│
├── admin/
│   ├── dashboard.php          # Admin dashboard
│   ├── pos.php                # Point of sales
│   ├── sales_reports.php      # Sales reports with filters
│   ├── inventory.php          # Product CRUD
│   ├── staff_duties.php       # Staff duty tracking
│   └── staff_management.php   # Staff account management
│
├── staff/
│   ├── dashboard.php          # Staff dashboard
│   └── inventory.php          # View-only inventory
│
├── assets/
│   └── css/
│       └── style.css          # Main stylesheet
│
├── index.php                  # Login page
├── logout.php                 # Logout handler
└── database.sql               # Database schema
```

## 🗄️ Database Schema

### Tables
- **users**: User accounts (admin & staff)
- **user_permissions**: Feature-based access control
- **product_categories**: Product categories
- **products**: Product inventory
- **sales**: Sales transactions
- **staff_duties**: Manual staff performance tracking

### Views
- **daily_sales_summary**: Aggregated daily sales
- **low_stock_products**: Products below threshold
- **staff_performance**: Staff performance metrics

## 🔒 Security Features

1. **Password Security**
   - Passwords hashed using `password_hash()` with PASSWORD_DEFAULT
   - Verification with `password_verify()`

2. **SQL Injection Prevention**
   - Prepared statements with PDO
   - Parameter binding for all queries

3. **Session Security**
   - Session regeneration on login
   - Session timeout handling
   - Role-based access checks

4. **Input Validation**
   - Server-side validation
   - HTML special chars escaping
   - Sanitized user inputs

## 📊 Usage Guide

### Admin Workflow
1. **Login** → Access admin dashboard
2. **View Dashboard** → Check sales, stock alerts
3. **Add Staff** → Staff Management → Create account with permissions
4. **Record Sale** → POS → Select product/service → Complete
5. **Check Reports** → Sales Reports → Filter by date
6. **Track Performance** → Staff Duties → Manual entry
7. **Manage Inventory** → Add/Edit/Delete products

### Staff Workflow
1. **Login** → Access staff dashboard
2. **View Assigned Modules** → Based on permissions
3. **Process Sales** → If POS access granted
4. **View Inventory** → Check product availability
5. **Track Performance** → View own sales & duties

## 🎨 Customization

### Change Colors
Edit `assets/css/style.css`:
```css
/* Primary gradient */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

### Add Categories
Login as admin → Directly in phpMyAdmin:
```sql
INSERT INTO product_categories (category_name) VALUES ('Your Category');
```

### Modify Permissions
Add new permission columns to `user_permissions` table

## ⚠️ Troubleshooting

### "Connection failed" error
- Check XAMPP MySQL is running
- Verify database name in `config/database.php`
- Ensure database was created successfully

### "Access denied" after login
- Clear browser cache/cookies
- Check user status is 'active' in database
- Verify permissions are set correctly

### Pages showing blank
- Enable PHP error reporting: `error_reporting(E_ALL);`
- Check Apache error logs in XAMPP

### CSS not loading
- Clear browser cache
- Check file path: `/salon_management_system/assets/css/style.css`
- Verify Apache is serving the correct directory

## 🔄 Future Enhancements

- [ ] Appointment scheduling system
- [ ] Customer database
- [ ] Automated email notifications
- [ ] Payroll calculation
- [ ] Advanced reporting (charts/graphs)
- [ ] Mobile responsive design improvements
- [ ] Export reports to PDF/Excel
- [ ] Multi-branch support

## 📝 License

This project is for educational purposes. Free to use and modify.

## 👨‍💻 Developer Notes

- PHP version: 7.4 or higher recommended
- MySQL version: 5.7 or higher
- All passwords stored with bcrypt hashing
- Uses PDO for database operations
- Session-based authentication
- Prepared statements prevent SQL injection

## 📞 Support

For issues or questions:
1. Check the troubleshooting section
2. Verify XAMPP services are running
3. Check database connection settings
4. Review Apache/PHP error logs

---

**Default Admin Login**: username: `admin` | password: `admin`

**Important**: Change the default admin password after first login!
