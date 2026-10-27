# INSTALLATION GUIDE
## Salon Management System - Step by Step Setup

---

## 📋 PREREQUISITES

Before you begin, make sure you have:
- ✅ A computer with Windows, macOS, or Linux
- ✅ At least 2GB free disk space
- ✅ Administrator/root access to install software
- ✅ Internet connection (for downloading XAMPP)

---

## 🚀 STEP 1: INSTALL XAMPP

### Windows Installation:
1. Download XAMPP from: https://www.apachefriends.org/download.html
2. Choose the latest version for Windows
3. Run the installer (xampp-windows-x64-installer.exe)
4. Click "Next" through the installation wizard
5. Install to default location: `C:\xampp`
6. Click "Finish" when installation completes

### Linux Installation:
```bash
# Download XAMPP
wget https://www.apachefriends.org/xampp-files/8.2.12/xampp-linux-x64-8.2.12-0-installer.run

# Make executable
chmod +x xampp-linux-x64-8.2.12-0-installer.run

# Run installer
sudo ./xampp-linux-x64-8.2.12-0-installer.run
```

### macOS Installation:
1. Download XAMPP for macOS from: https://www.apachefriends.org/
2. Open the .dmg file
3. Drag XAMPP to Applications folder
4. Open XAMPP from Applications

---

## 🔧 STEP 2: START XAMPP SERVICES

### Windows:
1. Open "XAMPP Control Panel" from Start Menu
2. Click "Start" button next to **Apache**
3. Click "Start" button next to **MySQL**
4. Both should show green "Running" status

### Linux:
```bash
sudo /opt/lampp/lampp start
```

### macOS:
1. Open XAMPP Manager from Applications
2. Click "Start" for Apache
3. Click "Start" for MySQL

**Verify Services:**
- Open browser and go to: http://localhost
- You should see XAMPP welcome page

---

## 📂 STEP 3: COPY PROJECT FILES

### Windows:
1. Locate the `salon_management_system` folder
2. Copy the entire folder
3. Paste it into: `C:\xampp\htdocs\`
4. Final path should be: `C:\xampp\htdocs\salon_management_system\`

### Linux:
```bash
sudo cp -r salon_management_system /opt/lampp/htdocs/
sudo chmod -R 755 /opt/lampp/htdocs/salon_management_system
```

### macOS:
```bash
sudo cp -r salon_management_system /Applications/XAMPP/htdocs/
sudo chmod -R 755 /Applications/XAMPP/htdocs/salon_management_system
```

---

## 🗄️ STEP 4: CREATE DATABASE

### Method 1: Using phpMyAdmin (Recommended)

1. **Open phpMyAdmin:**
   - Go to: http://localhost/phpmyadmin
   - You should see phpMyAdmin interface

2. **Import Database:**
   - Click "Import" tab at the top
   - Click "Choose File" button
   - Navigate to: `htdocs/salon_management_system/database.sql`
   - Select the file
   - Scroll down and click "Go" button
   - Wait for success message: "Import has been successfully finished"

3. **Verify Database:**
   - Click "salon_management" in left sidebar
   - You should see 7 tables and 3 views

### Method 2: Manual SQL Execution

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click "SQL" tab
3. Open `database.sql` file in a text editor
4. Copy ALL the SQL code
5. Paste it into the SQL query box
6. Click "Go"

---

## ✅ STEP 5: VERIFY INSTALLATION

### Check Files:
1. Verify folder exists: `htdocs/salon_management_system/`
2. Check these files are present:
   - ✓ index.php
   - ✓ database.sql
   - ✓ config/database.php
   - ✓ config/auth.php
   - ✓ admin/dashboard.php
   - ✓ assets/css/style.css

### Check Database:
1. Go to: http://localhost/phpmyadmin
2. Click "salon_management" database
3. Verify these tables exist:
   - ✓ users
   - ✓ user_permissions
   - ✓ products
   - ✓ product_categories
   - ✓ sales
   - ✓ staff_duties

### Check Default Admin:
1. In phpMyAdmin, click "users" table
2. Click "Browse"
3. You should see one user:
   - username: admin
   - role: admin
   - status: active

---

## 🌐 STEP 6: ACCESS THE SYSTEM

1. **Open Your Browser**
   - Chrome, Firefox, Edge, or Safari

2. **Navigate to:**
   ```
   http://localhost/salon_management_system
   ```

3. **Login with default credentials:**
   - Username: `admin`
   - Password: `admin`

4. **You should see:**
   - Admin Dashboard with sales statistics
   - Navigation menu on the left
   - Welcome message

---

## 🎉 STEP 7: FIRST TIME SETUP

### 1. Change Admin Password (Recommended):
   - Go to Staff Management
   - Click Edit on admin account
   - Enter new password
   - Click Update

### 2. Add First Staff Member:
   - Go to "Staff Management"
   - Fill in the form:
     - Full Name: (e.g., "Jane Doe")
     - Username: (e.g., "jane")
     - Password: (create a password)
     - Job Title: (e.g., "Hair Stylist")
     - Rate: (e.g., 500.00)
   - Check permissions you want to grant
   - Click "Add Staff"

### 3. Add Some Products:
   - Go to "Inventory"
   - Fill in product details:
     - Product Name: (e.g., "Shampoo Premium")
     - Category: Hair Products
     - Price: 250.00
     - Quantity: 50
     - Low Stock Threshold: 10
   - Click "Add Product"

### 4. Test Point of Sales:
   - Go to "Point of Sales"
   - Select transaction type: "Product"
   - Choose a product
   - Set quantity
   - Click "Complete Sale"

---

## ❗ TROUBLESHOOTING

### Problem: "Cannot connect to database"
**Solution:**
1. Check MySQL is running in XAMPP Control Panel
2. Verify database name is `salon_management` in phpMyAdmin
3. Check config/database.php has correct credentials:
   ```php
   DB_HOST = 'localhost'
   DB_USER = 'root'
   DB_PASS = ''
   DB_NAME = 'salon_management'
   ```

### Problem: "Page not found" (404 error)
**Solution:**
1. Verify Apache is running
2. Check URL is: http://localhost/salon_management_system
3. Verify folder is in htdocs directory
4. Check folder name is exactly: `salon_management_system`

### Problem: Blank white page
**Solution:**
1. Check PHP errors in XAMPP control panel logs
2. Verify all PHP files were copied correctly
3. Try accessing: http://localhost/salon_management_system/index.php

### Problem: Login not working
**Solution:**
1. Clear browser cache and cookies
2. Check database has admin user:
   - phpMyAdmin → salon_management → users table
3. Verify username is exactly: `admin`
4. Verify password is exactly: `admin`
5. Check user status is: `active`

### Problem: CSS not loading (page looks broken)
**Solution:**
1. Clear browser cache (Ctrl+F5 or Cmd+Shift+R)
2. Verify file exists: `assets/css/style.css`
3. Check browser console for errors (F12)
4. Try different browser

### Problem: Permission denied errors
**Solution:**

**Windows:**
- Right-click folder → Properties → Security
- Give Full Control to your user

**Linux/macOS:**
```bash
sudo chmod -R 755 /opt/lampp/htdocs/salon_management_system
sudo chown -R daemon:daemon /opt/lampp/htdocs/salon_management_system
```

---

## 📞 GETTING HELP

If you encounter issues:

1. **Check XAMPP Services:**
   - Both Apache and MySQL must be green/running

2. **Review Error Logs:**
   - XAMPP Control Panel → Logs button → View Apache Error Log

3. **Database Connection:**
   - Test phpMyAdmin access: http://localhost/phpmyadmin
   - Verify database exists

4. **Browser Console:**
   - Press F12 to open Developer Tools
   - Check Console tab for JavaScript errors
   - Check Network tab for failed requests

---

## ✨ NEXT STEPS

Once installation is successful:

1. ✅ Change default admin password
2. ✅ Create staff accounts
3. ✅ Add product inventory
4. ✅ Configure categories
5. ✅ Start recording sales
6. ✅ Track staff duties
7. ✅ Generate reports

---

## 🔐 SECURITY REMINDERS

⚠️ **IMPORTANT:**
- Change default admin password immediately
- Use strong passwords for all accounts
- Don't use this system on public/shared hosting without additional security
- This is designed for LOCAL use with XAMPP
- Keep XAMPP updated

---

## 📚 ADDITIONAL RESOURCES

- XAMPP Documentation: https://www.apachefriends.org/docs
- PHP Manual: https://www.php.net/manual/
- MySQL Documentation: https://dev.mysql.com/doc/

---

**Installation complete! You're ready to manage your salon! 🎉**

**Default Login:** username: `admin` | password: `admin`
