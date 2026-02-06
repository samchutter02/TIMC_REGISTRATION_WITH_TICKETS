# README: Tucson International Mariachi Conference Registration System

**This README serves as a comprehensive guide to the 2026 Tucson International Mariachi Conference registration system.**  
I wrote this for future devs taking over maintenance, updates, or troubleshooting. Think of this as the "Bible" for the program: it covers architecture, file purposes, workflows, database interactions, security notes, and more. The system is built in PHP, uses MySQL for data storage, Stripe for payments, and PHPMailer for emails. It supports group and individual registrations for workshops, with options for performances, hotels, and payments via credit card or purchase order (Jaime prefers "Transaction ID").

If you're new, start with the **Introduction** and **Setup Instructions**. Then, review the **Workflow** section to understand user flow, followed by **File-by-File Explanations** for deep dives.

---

### **AS OF 1/28/26 - FORM NOW SUPPORTS SAVE AND CONTINUE FUNCTIONALITY**  
The system now allows users to save partial registrations and resume later via an emailed token-based link. This uses a new database table (`partial_registrations`) and dedicated scripts (`save-partial.php` and `resume.php`). See details below.

---

## **Introduction**

### **System Overview**
This is a web-based registration system for the TIMC event. Users (directors, individuals, or admins) fill out a form to register groups or individuals for workshops, performances, and related activities.  

**Key Features**:
- **Registration Types**: Group (e.g., school bands) or Individual.
- **Workshop Types**: Mariachi (instrument-based) or Folklorico (dance-focused).
- **Add-Ons**: Showcase performances (with song submissions), Garibaldi performances, hotel stays, competition exclusion.
- **Payments**: Credit card (via Stripe) or Purchase Order (PO, for schools/groups).
- **Save and Continue**: Users can save partial progress and resume via emailed link (valid for 7 days).
- **Data Storage**: MySQL database stores directors, performers, groups, songs, and partial registrations.
- **Emails**: Automated confirmations sent to users, directors, and admins; plus resume links for partial saves.
- **Validation**: Duplicate group name checks, form validation via JS and PHP.
- **Closure Mechanism**: A sentinel file (`.reg-closed`) disables registration. Literally so easy to toggle the entire registration system on and off.

The system emphasizes simplicity: no user accounts, session-based state management, and minimal dependencies. It's hosted on a server with PHP support.

### **Key Technologies**
- **PHP**: Version 7+ (code uses PDO, sessions, $_POST, and autoloading via Composer).
- **MySQL**: For storage.
- **Stripe API**: For credit card payments.
- **PHPMailer**: For sending HTML emails via SMTP (configured for localhost/no auth).
- **Composer**: For dependencies (Stripe, PHPMailer).
- **JavaScript**: Client-side form enhancements (dynamic rows, validations, toggles).
- **HTML/CSS**: Basic styling for form and confirmations.

### **Assumptions and Limitations**
- No authentication: Anyone can access the form.
- Sessions store form data temporarily.
- No file uploads.
- Emails are sent synchronously (could block if slow).
- No refunds or edits post-submission.
- Partial saves overwrite existing data for the same group name (upsert logic).

---

## **System Requirements**

- **Server**: PHP 7.4+ with extensions: PDO (MySQL), session, fileinfo.
- **Database**: MySQL 5.7+ with a database.
- **Composer**: Installed locally for dependencies.
- **Stripe Account**: For payments; use test keys initially.
- **Email Setup**: SMTP server (code assumes localhost port 25, no auth).
- **File Permissions**: Writable directories for sessions and logs.
- **Browser Support**: Modern browsers (form uses flexbox, ES6 JS).

---

## **Setup Instructions**

1. **Clone/Extract Code**:
   - Visit my repo (ask me for access it is private). Clone it.

2. **Install Dependencies**:
   - Run `composer install` in root to get Stripe and PHPMailer.

3. **Configure Environment (.env File)**:
   - Create `.env` in the root (ABSOLUTELY no spaces/quotes in values):
     ```
     DB_HOST=localhost
     DB_NAME=timc_reg
     DB_USER=root
     DB_PASS= ask me ;)
     DB_CHARSET=utf8mb4
     ```
   - This is loaded in `process.php`, `charge.php`, `save-partial.php`, and `resume.php` for DB connections.

4. **Database Setup** - if for whatever reason you need to remake the DB:
   - Create database and tables:
     ```sql
     CREATE DATABASE timc_reg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

     USE timc_reg;

     CREATE TABLE directors (
         id INT AUTO_INCREMENT PRIMARY KEY,
         group_name VARCHAR(255) NOT NULL,
         first_name VARCHAR(100),
         last_name VARCHAR(100),
         street_address VARCHAR(255),
         city VARCHAR(100),
         state VARCHAR(50),
         zip_code VARCHAR(20),
         daytime_phone VARCHAR(50),
         cell_phone VARCHAR(50),
         email VARCHAR(255),
         d2_first_name VARCHAR(100),
         d2_last_name VARCHAR(100),
         d2_cell_phone VARCHAR(50),
         d2_daytime_phone VARCHAR(50),
         d2_email VARCHAR(255)
     );

     CREATE TABLE performers (
         id INT AUTO_INCREMENT PRIMARY KEY,
         group_name VARCHAR(255) NOT NULL,
         first_name VARCHAR(100),
         last_name VARCHAR(100),
         age INT,
         gender VARCHAR(50),
         grade VARCHAR(50),
         race VARCHAR(100),
         class VARCHAR(100),  -- e.g., 'Violin', 'Dance'
         level VARCHAR(50),   -- e.g., 'I', 'Master'
         cost DECIMAL(10,2)
     );

     CREATE TABLE groups (
         id INT AUTO_INCREMENT PRIMARY KEY,
         group_name VARCHAR(255) NOT NULL UNIQUE,
         group_type VARCHAR(50),  -- e.g., 'School', 'Community'
         workshop_type VARCHAR(50),  -- 'Mariachi', 'Folklorico'
         showcase_performance VARCHAR(3),  -- 'Yes'/'No'
         garibaldi_performance VARCHAR(3),  -- 'yes'/'no'
         school_name VARCHAR(255),
         user_first_name VARCHAR(100),
         user_last_name VARCHAR(100),
         user_email VARCHAR(255),
         user_phone VARCHAR(50),
         total_cost DECIMAL(10,2),
         po_number VARCHAR(50),
         registration_date DATETIME,
         paid VARCHAR(3),  -- 'Yes'/'No'
         competition_exclusion VARCHAR(3),  -- 'yes'/'no'
         hotel VARCHAR(3),  -- 'yes'/'no'
         hotel_name VARCHAR(255),
         hotel_duration INT,  -- nights
         payment_1_date DATETIME,
         payment_1_amount DECIMAL(10,2),
         payment_1_method VARCHAR(50)  -- 'credit_card'/'purchase_order'
     );

     CREATE TABLE songs (
         id INT AUTO_INCREMENT PRIMARY KEY,
         group_name VARCHAR(255) NOT NULL,
         song_1_title VARCHAR(255),
         song_1_length VARCHAR(10),  -- 'MM:SS'
         song_2_title VARCHAR(255),
         song_2_length VARCHAR(10),
         song_3_title VARCHAR(255),
         song_3_length VARCHAR(10)
     );

     CREATE TABLE partial_registrations (
         token VARCHAR(255) NOT NULL,
         data_json TEXT NOT NULL,
         email VARCHAR(255) NOT NULL,
         group_name VARCHAR(255) NOT NULL UNIQUE,
         created_at DATETIME NOT NULL,
         expires_at DATETIME NOT NULL
     );
     ```
   - Indexes: Add UNIQUE on `groups.group_name` and `partial_registrations.group_name`.

5. **Stripe Keys**:
   - In `charge.php`: Replace `sk_test_h**********************` with secret key.
   - In `invoice.php`: Replace `pk_test_5**********************` with publishable key.
   - For production: Use live keys and remove test mode.

6. **Email Configuration**:
   - In `send-email.php`: Update SMTP settings (Host, Auth, etc.) for production.

7. **Registration Closure**:
   - Create an empty file `.reg-closed` in root to disable forms (displays existing `registration-closed.php` file instead of `index.php`).

8. **Testing**:
   - Open `index.php` in browser.
   - Test flows: Group/Individual, Mariachi/Folklorico, CC/PO, and Save/Resume.
   - Monitor PHP error logs.

***

## **Workflow: How Registration Works**

### **High-Level User Flow**
1. **Access Form**: User visits `index.php` (main form).
   - If `.reg-closed` exists in directory, shows closure message.
2. **Fill Form**:
   - Select type (Group/Individual), workshop (Mariachi/Folklorico).
   - Enter director/assistant details, performers (dynamic rows).
   - Options: Showcase (songs), Garibaldi, Hotel, Competition exclusion.
   - Payment: CC or PO (PO hidden for individuals).
   - **Save Partial**: At any point, submit partial data (requires group name and email) to `save-partial.php`.
3. **Save and Continue (Partial Registration)**:
   - Posts to `save-partial.php`: Validates, saves JSON data to `partial_registrations`, generates token, sends resume email with link.
   - User sees success page with email confirmation.
   - To resume: Visit `resume.php?token=...` – loads data into session, redirects to `index.php` with pre-filled form.
4. **Submit (Full Registration)**:
   - Posts to `process.php`.
   - Validates group name uniqueness.
   - For PO: Inserts data immediately, sends emails, redirects to `confirmation-po.php`.
   - For CC: Stores in session, redirects to `invoice.php`.
5. **Payment (CC Only)**:
   - `invoice.php`: Review cart, enter card details via Stripe.
   - Posts to `charge.php`: Processes payment.
     - Success: Inserts data, sends emails, shows success page.
     - Failure: Shows error page.
6. **Confirmation**:
   - Emails sent to director, user (if different), and admin.
   - User sees on-screen confirmation.

### **Session Management**
- `$_SESSION['form_data']`: Raw POST data.
- `$_SESSION['cart']`: Calculated totals, PO number, etc.
- `$_SESSION['partial_resume_data']`: Loaded from DB for resumes.
- Cleared on success.

### **Error Handling**
- Duplicate group names: Shows error page.
- Payment failures: Stripe exceptions handled.
- DB errors: Rollbacks in transactions.
- Expired/Invalid Tokens: Redirects to `index.php?resume=expired`.

***

## **File-by-File Explanations**

*Each file's purpose, key code sections, and interactions :)*

### **index.php (Main Registration Form)**
**Purpose**: The entry point and core UI. Renders a multi-section (non-paginated) form for data entry. Uses JS for dynamic behavior (e.g., adding performer rows, toggling fields). Supports pre-filling from partial resumes via session.

**Key Sections**:
- **Header/Styles**: Custom CSS for layout (cards, tables, buttons).
- **Form Structure**:
  - Section 1: Registration type (Group/Individual), workshop type.
  - Section 2: Group/Individual name, school (if School group).
  - Section 3: Director details, assistant (toggleable).
  - Section 4: Performers table (dynamic rows via JS: add/remove, cost calc).
  - Section 5: Showcase (toggle songs), Garibaldi, Competition exclusion.
  - Section 6: Hotel details (toggle).
  - Section 7: Payment options (CC/PO).
- **JavaScript**:
  - Dynamic: Add/remove performer rows, update costs/total.
  - Toggles: Hide/show fields based on type (e.g., no PO for individuals, enforce Dance for Folklorico).
  - Validations: Song durations, required fields.
  - **Resume Support**: Checks `$_SESSION['partial_resume_data']` to pre-fill fields.
- **Submission**: Posts to `process.php` (full) or `save-partial.php` (partial).
- **Notes**: Truncated in prompt (31246 chars), but full form includes all fields. Update costs here if prices change.

### **save-partial.php (Partial Save Handler)**
**Purpose**: Handles saving incomplete form data for later resumption. Validates minimal fields, stores data in DB, generates a token, and sends a resume email.

**Key Logic**:
- **Validation**: Checks action='save_partial', requires group name and valid email.
- **Data Prep**: Copies $_POST (excludes sensitive fields like tokens).
- **DB Interaction**: Connects via PDO, UPSERTs into `partial_registrations` (updates if group_name exists).
  - Generates 24-char hex token.
  - Sets 7-day expiration.
- **Email**: Calls `sendResumeLink` from `send-email.php` with resume URL.
- **Output**: Renders a styled success HTML page with email info and back link.
- **Error Handling**: Custom `dieWithMessage` for failures (e.g., invalid email, DB error).
- **Notes**: Uses lowercase group_name for key. Requires `send-email.php`. Overwrites prior saves for same group.

### **resume.php (Resume Handler)**
**Purpose**: Loads saved partial data using a token from the URL query. Validates token and expiration, sets session data, and redirects to form.

**Key Logic**:
- **Input**: Gets `token` from $_GET.
- **DB Interaction**: Connects via PDO, queries `partial_registrations` for valid/non-expired token.
- **Success**: Decodes JSON data into `$_SESSION['partial_resume_data']`, redirects to `index.php`.
- **Failure**: Redirects to `index.php?resume=expired` (handles invalid/expired tokens).
- **Notes**: Does not delete the record (allows multiple resumes). Optional: Add delete after load for single-use.

### **process.php (Form Processor)**
**Purpose**: Handles full form submission from `index.php`. Validates, calculates, and either inserts (for PO) or prepares for payment (CC).

**Key Logic**:
- **Checks**: Registration closed? Duplicate group name (queries `groups` table)?
- **Calculations**: PO number from cell phone suffix, total cost from performers.
- **For PO**:
  - Begin DB transaction.
  - Insert into `directors`, `performers`, `groups`, `songs` (if showcase).
  - Commit, send emails via `send-email.php`.
  - Set session cart, redirect to `confirmation-po.php`.
- **For CC**:
  - Set session cart, redirect to `invoice.php` (no DB insert yet).
- **Env Loading**: Parses `.env` for DB creds.
- **Error Handling**: Rollback on exceptions, die with message.
- **Notes**: Enforces 'Dance' for Folklorico. Songs only if showcase and not individual.

### **confirmation-po.php (PO Confirmation Page)**
**Purpose**: Displays success message for PO registrations. Redirects to main site after 8 seconds.

**Key Elements**:
- Uses session cart for details (group, PO#, total).
- Simple HTML/CSS: Box with info, auto-redirect JS.
- **Security**: Checks session/payment method; redirects if invalid.
- **Notes**: No DB/email here; handled in `process.php`.

### **charge.php (Stripe Payment Processor)**
**Purpose**: Handles credit card payments from `invoice.php`. On success, inserts data and sends emails.

**Key Logic**:
- **Stripe Integration**: Loads API key (test; update for prod), creates charge.
- **Success**:
  - Begin DB transaction.
  - Insert into `directors`, `performers`, `groups`, `songs` (similar to `process.php`).
  - Send emails via `send-email.php`.
  - Clear session.
  - Show success HTML (with charge ID, receipt email).
- **Failure**: Catch Stripe exceptions, show error HTML.
- **Env Loading**: Same as `process.php` for DB.
- **Notes**: Costs rounded to cents. Emails to director, user (if different), admin. Folklorico enforces 'Dance'.

### **send-email.php (Email Sender)**
**Purpose**: Contains functions for sending confirmation and resume emails.

**Functions**:
- **sendRegistrationConfirmation**: Builds HTML email with summary (tables for details, participants).
  - Uses PHPMailer (SMTP localhost).
  - Body: Registration summary, director info, participants table, choices.
  - Sends to recipient + BCC admin.
- **sendResumeLink**: Builds HTML email with resume link and instructions.
- **Notes**: Hardcoded admin email `info@tucsonmariachi.org`. Error logs failures. Customize styles/HTML for branding.

### **invoice.php (CC Review & Payment Page)**
**Purpose**: Displays cart review and Stripe card form for CC payments.

**Key Elements**:
- Shows group, total, PO# from session.
- Stripe JS: Creates card element, generates token on submit.
- Posts token to `charge.php`.
- **Notes**: Basic styling. Update publishable key for prod.

### **Other Files (Mentioned or Inferred)**
- **registration-closed.php**: Not provided, but shown if `.reg-closed` exists. Likely a simple "Closed" message with redirect.
- **vendor/autoload.php**: Composer-generated; loads Stripe/PHPMailer.
- **.reg-closed**: Empty sentinel file to close registration.
- **.env**: Config file (DB creds); never commit to repo.

***

## **Database Schema Details**

Tables:
- **directors**: One per group; primary contact + optional assistant.
- **performers**: Multiple per group; participant details + cost.
- **groups**: Core entity; aggregates type, payments, options.
- **songs**: Optional; up to 3 songs for showcases.
- **partial_registrations**: For save/resume; stores JSON data with token and expiration.

**Relationships**: Linked by `group_name` (string; consider ID for efficiency).

**Best Practices**: Add timestamps, indexes on `group_name`. Backup regularly.

***

## **Security Considerations**

- **Input Sanitization**: Uses `htmlspecialchars` in outputs; PDO prepared statements prevent SQL injection.
- **Sessions**: Sensitive data (form/cart) in sessions; clear on success.
- **Stripe**: Tokens prevent card data on server; use HTTPS in prod.
- **Emails**: Validate addresses; no attachments to avoid spam.
- **Tokens (for Resume)**: Randomly generated (bin2hex), time-limited.
- **Vulnerabilities**:
  - No CSRF protection (add tokens).
  - Env file: .htaccess deny access.
  - Duplicate checks: Race conditions possible (use transactions).
  - Error Logs: Expose exceptions? Use try-catch.
- **Recommendations**: Add CAPTCHA for spam, rate limiting, input validation (e.g., email regex).

***

## **Maintenance and Extension Tips**

- **Updating Costs**: Hardcoded in JS (`index.php`) and PHP (`process.php`, `charge.php`); sync them.
- **Adding Fields**: Update form, sessions, DB inserts, emails.
- **Partial Saves**: Extend expiration or add cleanup cron for expired records.
- **Logging**: Enhance error_log for audits.
- **Testing**: Use Stripe test cards (e.g., 4242424242424242). Test resume with invalid/expired tokens.
- **Scaling**: For high traffic, async emails (queue), optimize queries.
- **Versions**: Monitor dependencies (Composer update).
- **Contact**: For questions, email info@tucsonmariachi.org (admin).