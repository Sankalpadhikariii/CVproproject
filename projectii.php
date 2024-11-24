<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

// Database connection
$conn = mysqli_connect("localhost", "root", "", "cv_builder");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Add new tables for additional features
$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    summary TEXT,
    profile_image VARCHAR(255),
    template_choice VARCHAR(50) DEFAULT 'modern',
    color_scheme VARCHAR(50) DEFAULT 'blue',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS education (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    institution VARCHAR(100),
    degree VARCHAR(100),
    field VARCHAR(100),
    start_date DATE,
    end_date DATE,
    gpa VARCHAR(10),
    achievements TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS experience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    company VARCHAR(100),
    position VARCHAR(100),
    start_date DATE,
    end_date DATE,
    description TEXT,
    achievements TEXT,
    location VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    skill_name VARCHAR(100),
    proficiency VARCHAR(20),
    category VARCHAR(50),
    years_experience INT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(100),
    description TEXT,
    technologies TEXT,
    url VARCHAR(255),
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS certifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(100),
    issuer VARCHAR(100),
    date_obtained DATE,
    expiry_date DATE,
    credential_id VARCHAR(100),
    url VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    language_name VARCHAR(50),
    proficiency_level VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS cv_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    version_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    content TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);";

mysqli_multi_query($conn, $sql);

// Handle image upload
function handleImageUpload($user_id) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['profile_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $newname = "profile_{$user_id}." . $filetype;
            $upload_path = "uploads/" . $newname;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                return $upload_path;
            }
        }
    }
    return null;
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['personal_info'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $summary = mysqli_real_escape_string($conn, $_POST['summary']);
        $template = mysqli_real_escape_string($conn, $_POST['template_choice']);
        $color_scheme = mysqli_real_escape_string($conn, $_POST['color_scheme']);

        $sql = "INSERT INTO users (name, email, phone, address, summary, template_choice, color_scheme) 
                VALUES ('$name', '$email', '$phone', '$address', '$summary', '$template', '$color_scheme')";
        mysqli_query($conn, $sql);
        $_SESSION['user_id'] = mysqli_insert_id($conn);

        // Handle profile image upload
        $image_path = handleImageUpload($_SESSION['user_id']);
        if ($image_path) {
            $sql = "UPDATE users SET profile_image = '$image_path' WHERE id = {$_SESSION['user_id']}";
            mysqli_query($conn, $sql);
        }
    }

    // Handle project submission
    if (isset($_POST['project'])) {
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $technologies = mysqli_real_escape_string($conn, $_POST['technologies']);
        $url = mysqli_real_escape_string($conn, $_POST['url']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $user_id = $_SESSION['user_id'];

        $sql = "INSERT INTO projects (user_id, title, description, technologies, url, start_date, end_date) 
                VALUES ('$user_id', '$title', '$description', '$technologies', '$url', '$start_date', '$end_date')";
        mysqli_query($conn, $sql);
    }

    // Handle certification submission
    if (isset($_POST['certification'])) {
        $title = mysqli_real_escape_string($conn, $_POST['cert_title']);
        $issuer = mysqli_real_escape_string($conn, $_POST['issuer']);
        $date_obtained = $_POST['date_obtained'];
        $expiry_date = $_POST['expiry_date'];
        $credential_id = mysqli_real_escape_string($conn, $_POST['credential_id']);
        $url = mysqli_real_escape_string($conn, $_POST['cert_url']);
        $user_id = $_SESSION['user_id'];

        $sql = "INSERT INTO certifications (user_id, title, issuer, date_obtained, expiry_date, credential_id, url) 
                VALUES ('$user_id', '$title', '$issuer', '$date_obtained', '$expiry_date', '$credential_id', '$url')";
        mysqli_query($conn, $sql);
    }

    // Handle language submission
    if (isset($_POST['language'])) {
        $language = mysqli_real_escape_string($conn, $_POST['language_name']);
        $level = mysqli_real_escape_string($conn, $_POST['proficiency_level']);
        $user_id = $_SESSION['user_id'];

        $sql = "INSERT INTO languages (user_id, language_name, proficiency_level) 
                VALUES ('$user_id', '$language', '$level')";
        mysqli_query($conn, $sql);
    }

    // Save CV version
    if (isset($_POST['save_version'])) {
        $version_name = mysqli_real_escape_string($conn, $_POST['version_name']);
        $user_id = $_SESSION['user_id'];
        $content = generateCVContent($conn, $user_id);

        $sql = "INSERT INTO cv_versions (user_id, version_name, content) 
                VALUES ('$user_id', '$version_name', '$content')";
        mysqli_query($conn, $sql);
    }
}

// Generate CV content based on template
function generateCVContent($conn, $user_id) {
    $sql = "SELECT template_choice, color_scheme FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $sql);
    $style_info = mysqli_fetch_assoc($result);
    
    $template = $style_info['template_choice'];
    $color_scheme = $style_info['color_scheme'];
    
    // Get template-specific styling
    $styles = getTemplateStyles($template, $color_scheme);
    
    [$user, $education, $experience, $skills, $projects, $certifications, $languages] = fetchUserData($conn, $user_id);

    $cv = "<div style='{$styles['container']}'>";
    
    // Header section with profile image
    if (!empty($user['profile_image'])) {
        $cv .= "<div style='{$styles['header']}'>";
        $cv .= "<img src='{$user['profile_image']}' style='width: 150px; border-radius: 50%;' alt='Profile Photo'>";
    }
    
    $cv .= "<h1 style='{$styles['name']}'>{$user['name']}</h1>";
    $cv .= "<div style='{$styles['contact']}'>";
    $cv .= "<p><strong>Email:</strong> {$user['email']}<br>";
    $cv .= "<strong>Phone:</strong> {$user['phone']}<br>";
    $cv .= "<strong>Address:</strong> {$user['address']}</p></div>";
    
    // Professional Summary
    $cv .= "<div style='{$styles['section']}'>";
    $cv .= "<h2 style='{$styles['heading']}'>Professional Summary</h2>";
    $cv .= "<p>{$user['summary']}</p></div>";

    // Education
    $cv .= "<div style='{$styles['section']}'>";
    $cv .= "<h2 style='{$styles['heading']}'>Education</h2><ul style='{$styles['list']}'>";
    while ($edu = mysqli_fetch_assoc($education)) {
        $cv .= "<li style='{$styles['listItem']}'>";
        $cv .= "<strong>{$edu['degree']} in {$edu['field']}</strong><br>";
        $cv .= "{$edu['institution']}<br>";
        $cv .= "{$edu['start_date']} to {$edu['end_date']}";
        if (!empty($edu['gpa'])) {
            $cv .= "<br>GPA: {$edu['gpa']}";
        }
        if (!empty($edu['achievements'])) {
            $cv .= "<br>Achievements: {$edu['achievements']}";
        }
        $cv .= "</li>";
    }
    $cv .= "</ul></div>";

    // Experience
    $cv .= "<div style='{$styles['section']}'>";
    $cv .= "<h2 style='{$styles['heading']}'>Work Experience</h2><ul style='{$styles['list']}'>";
    while ($exp = mysqli_fetch_assoc($experience)) {
        $cv .= "<li style='{$styles['listItem']}'>";
        $cv .= "<strong>{$exp['position']} at {$exp['company']}</strong><br>";
        $cv .= "{$exp['location']}<br>";
        $cv .= "{$exp['start_date']} to {$exp['end_date']}<br>";
        $cv .= "{$exp['description']}";
        if (!empty($exp['achievements'])) {
            $cv .= "<br>Key Achievements: {$exp['achievements']}";
        }
        $cv .= "</li>";
    }
    $cv .= "</ul></div>";

    // Projects
    if (mysqli_num_rows($projects) > 0) {
        $cv .= "<div style='{$styles['section']}'>";
        $cv .= "<h2 style='{$styles['heading']}'>Projects</h2><ul style='{$styles['list']}'>";
        while ($project = mysqli_fetch_assoc($projects)) {
            $cv .= "<li style='{$styles['listItem']}'>";
            $cv .= "<strong>{$project['title']}</strong><br>";
            $cv .= "{$project['description']}<br>";
            $cv .= "Technologies: {$project['technologies']}<br>";
            if (!empty($project['url'])) {
                $cv .= "URL: <a href='{$project['url']}'>{$project['url']}</a><br>";
            }
            $cv .= "{$project['start_date']} to {$project['end_date']}";
            $cv .= "</li>";
        }
        $cv .= "</ul></div>";
    }

    // Skills with categories
    $cv .= "<div style='{$styles['section']}'>";
    $cv .= "<h2 style='{$styles['heading']}'>Skills</h2>";
    $categories = [];
    while ($skill = mysqli_fetch_assoc($skills)) {
        $category = $skill['category'] ?? 'Other';
        if (!isset($categories[$category])) {
            $categories[$category] = [];
        }
        $categories[$category][] = $skill;
    }
    
    foreach ($categories as $category => $categorySkills) {
        $cv .= "<h3 style='{$styles['subheading']}'>{$category}</h3><ul style='{$styles['list']}'>";
        foreach ($categorySkills as $skill) {
            $cv .= "<li style='{$styles['listItem']}'>";
            $cv .= "<strong>{$skill['skill_name']}</strong> - {$skill['proficiency']}";
            if (!empty($skill['years_experience'])) {
                $cv .= " ({$skill['years_experience']} years)";
            }
            $cv .= "</li>";
        }
        $cv .= "</ul>";
    }
    $cv .= "</div>";

    // Certifications
    if (mysqli_num_rows($certifications) > 0) {
        $cv .= "<div style='{$styles['section']}'>";
        $cv .= "<h2 style='{$styles['heading']}'>Certifications</h2><ul style='{$styles['list']}'>";
        while ($cert = mysqli_fetch_assoc($certifications)) {
            $cv .= "<li style='{$styles['listItem']}'>";
            $cv .= "<strong>{$cert['title']}</strong> - {$cert['issuer']}<br>";
            $cv .= "Obtained: {$cert['date_obtained']}";
            if (!empty($cert['expiry_date'])) {
                $cv .= " (Expires: {$cert['expiry_date']})";
            }
            if (!empty($cert['credential_id'])) {
                $cv .= "<br>Credential ID: {$cert['credential_id']}";
            }
            if (!empty($cert['url'])) {
                $cv .= "<br>Verify at: <a href='{$cert['url']}'>{$cert['url']}</a>";
            }
            $cv .= "</li>";
        }
        $cv .= "</ul></div>";
    }

    // Languages
    if (mysqli_num_rows($languages) > 0) {
        $cv .= "<div style='{$styles['section']}'>";
        $cv .= "<h2 style='{$styles['heading']}'>Languages</h2><ul style='{$styles['list']}'>";
        while ($lang = mysqli_fetch_assoc($languages)) {
            $cv .= "<li style='{$styles['listItem']}'>";
            $cv .= "<li style='{$styles['listItem']}'>";
$cv .= "<strong>{$lang['language_name']}</strong> - {$lang['proficiency_level']}";
$cv .= "</li>";
        }
        $cv .= "</ul></div>";
    }

    $cv .= "</div>";
    return $cv;
}

// Function to fetch all user data
function fetchUserData($conn, $user_id) {
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
    
    $education = mysqli_query($conn, "SELECT * FROM education WHERE user_id = $user_id ORDER BY end_date DESC");
    $experience = mysqli_query($conn, "SELECT * FROM experience WHERE user_id = $user_id ORDER BY end_date DESC");
    $skills = mysqli_query($conn, "SELECT * FROM skills WHERE user_id = $user_id ORDER BY category, skill_name");
    $projects = mysqli_query($conn, "SELECT * FROM projects WHERE user_id = $user_id ORDER BY end_date DESC");
    $certifications = mysqli_query($conn, "SELECT * FROM certifications WHERE user_id = $user_id ORDER BY date_obtained DESC");
    $languages = mysqli_query($conn, "SELECT * FROM languages WHERE user_id = $user_id ORDER BY language_name");
    
    return [$user, $education, $experience, $skills, $projects, $certifications, $languages];
}

// Function to get template-specific styles
function getTemplateStyles($template, $color_scheme) {
    $colors = [
        'blue' => ['primary' => '#2c3e50', 'secondary' => '#3498db', 'text' => '#2c3e50'],
        'green' => ['primary' => '#27ae60', 'secondary' => '#2ecc71', 'text' => '#2c3e50'],
        'red' => ['primary' => '#c0392b', 'secondary' => '#e74c3c', 'text' => '#2c3e50']
    ];
    
    $selected_colors = $colors[$color_scheme] ?? $colors['blue'];
    
    $base_styles = [
        'container' => 'max-width: 1200px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;',
        'header' => 'text-align: center; margin-bottom: 30px;',
        'name' => 'color: ' . $selected_colors['primary'] . '; font-size: 2.5em; margin-bottom: 10px;',
        'contact' => 'margin-bottom: 20px;',
        'section' => 'margin-bottom: 25px;',
        'heading' => 'color: ' . $selected_colors['primary'] . '; border-bottom: 2px solid ' . $selected_colors['secondary'] . '; padding-bottom: 5px; margin-bottom: 15px;',
        'subheading' => 'color: ' . $selected_colors['secondary'] . '; margin: 10px 0;',
        'list' => 'list-style-type: none; padding: 0;',
        'listItem' => 'margin-bottom: 15px; line-height: 1.6;'
    ];
    
    switch ($template) {
        case 'modern':
            // Modern template modifications
            $base_styles['container'] .= 'background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1);';
            $base_styles['heading'] .= 'font-weight: 300;';
            $base_styles['listItem'] .= 'padding-left: 20px; border-left: 3px solid ' . $selected_colors['secondary'] . ';';
            break;
            
        case 'classic':
            // Classic template modifications
            $base_styles['container'] .= 'background: #fafafa;';
            $base_styles['heading'] .= 'font-weight: bold;';
            $base_styles['listItem'] .= 'border-bottom: 1px solid #eee;';
            break;
            
        case 'minimal':
            // Minimal template modifications
            $base_styles['container'] .= 'background: white;';
            $base_styles['heading'] = 'color: ' . $selected_colors['primary'] . '; margin-bottom: 15px; font-size: 1.5em;';
            $base_styles['listItem'] .= 'padding: 10px 0;';
            break;
    }
    
    return $base_styles;
}

// Function to export CV as PDF
function exportAsPDF($user_id) {
    require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('CV Builder');
    $pdf->SetTitle('CV');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Get CV content
    $cv_content = generateCVContent($GLOBALS['conn'], $user_id);
    
    // Write HTML content
    $pdf->writeHTML($cv_content, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output('cv.pdf', 'D');
}

// Handle PDF export request
if (isset($_POST['export_pdf']) && isset($_SESSION['user_id'])) {
    exportAsPDF($_SESSION['user_id']);
}

// Close database connection
mysqli_close($conn);
?>