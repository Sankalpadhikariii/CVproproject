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

// Define the getTemplateStyles function
function getTemplateStyles($template, $color_scheme) {
    // Define basic styles
    $styles = [
        'container' => 'font-family: Arial, sans-serif; line-height: 1.6;',
        'header' => 'text-align: center; margin-bottom: 20px;',
        'name' => 'font-size: 36px; font-weight: bold;',
        'contact' => 'font-size: 16px; color: #555;',
        'section' => 'margin-bottom: 20px;',
        'heading' => 'font-size: 24px; font-weight: bold; margin-bottom: 10px;',
        'subheading' => 'font-size: 20px; font-weight: bold; margin-top: 10px;',
        'list' => 'list-style-type: none; padding-left: 0;',
        'listItem' => 'font-size: 16px; color: #555;',
    ];

    // Apply color scheme adjustments
    if ($color_scheme == 'dark') {
        $styles['container'] .= ' background-color: #333; color: white;';
        $styles['name'] .= ' color: #FF6347;';  // Tomato color for the name
    } else {
        $styles['container'] .= ' background-color: #fff; color: black;';
    }

    // Template-specific styles
    if ($template == 'simple') {
        // No additional styling needed for the simple template
    } elseif ($template == 'modern') {
        $styles['container'] .= ' padding: 20px;';
        $styles['header'] .= ' border-bottom: 2px solid #ddd;';
    }

    return $styles;
}

// Define the fetchUserData function
function fetchUserData($conn, $user_id) {
    // Fetch user data (personal info, education, experience, skills, etc.)
    $user_sql = "SELECT * FROM users WHERE id = $user_id";
    $user_result = mysqli_query($conn, $user_sql);
    $user_data = mysqli_fetch_assoc($user_result);

    $education_sql = "SELECT * FROM education WHERE user_id = $user_id";
    $education_result = mysqli_query($conn, $education_sql);
    $education_data = mysqli_fetch_all($education_result, MYSQLI_ASSOC);

    $experience_sql = "SELECT * FROM experience WHERE user_id = $user_id";
    $experience_result = mysqli_query($conn, $experience_sql);
    $experience_data = mysqli_fetch_all($experience_result, MYSQLI_ASSOC);

    $skills_sql = "SELECT * FROM skills WHERE user_id = $user_id";
    $skills_result = mysqli_query($conn, $skills_sql);
    $skills_data = mysqli_fetch_all($skills_result, MYSQLI_ASSOC);

    $projects_sql = "SELECT * FROM projects WHERE user_id = $user_id";
    $projects_result = mysqli_query($conn, $projects_sql);
    $projects_data = mysqli_fetch_all($projects_result, MYSQLI_ASSOC);

    return [
        'user' => $user_data,
        'education' => $education_data,
        'experience' => $experience_data,
        'skills' => $skills_data,
        'projects' => $projects_data,
    ];
}

// Generate CV content dynamically based on user data
function generateCVContent($conn, $user_id) {
    $data = fetchUserData($conn, $user_id);
    $user = $data['user'];
    $education = $data['education'];
    $experience = $data['experience'];
    $skills = $data['skills'];
    $projects = $data['projects'];

    $content = "<div style='padding: 20px;'>";
    $content .= "<div style='text-align: center;'>";
    $content .= "<h1 style='font-size: 36px;'>" . $user['name'] . "</h1>";
    $content .= "<p style='font-size: 16px;'>" . $user['email'] . " | " . $user['phone'] . "</p>";
    $content .= "<p style='font-size: 16px;'>" . $user['address'] . "</p>";
    $content .= "</div>";

    // Add Summary Section
    $content .= "<div style='margin-top: 20px;'><h2 style='font-size: 24px;'>Summary</h2>";
    $content .= "<p>" . nl2br($user['summary']) . "</p></div>";

    // Add Education Section
    $content .= "<div style='margin-top: 20px;'><h2 style='font-size: 24px;'>Education</h2><ul>";
    foreach ($education as $edu) {
        $content .= "<li style='font-size: 16px;'>" . $edu['degree'] . " in " . $edu['field'] . " from " . $edu['institution'] . " (" . $edu['start_date'] . " - " . $edu['end_date'] . ")";
        if ($edu['gpa']) {
            $content .= " - GPA: " . $edu['gpa'];
        }
        if ($edu['achievements']) {
            $content .= " - Achievements: " . $edu['achievements'];
        }
        $content .= "</li>";
    }
    $content .= "</ul></div>";

    // Add Experience Section
    $content .= "<div style='margin-top: 20px;'><h2 style='font-size: 24px;'>Experience</h2><ul>";
    foreach ($experience as $exp) {
        $content .= "<li style='font-size: 16px;'>" . $exp['position'] . " at " . $exp['company'] . " (" . $exp['start_date'] . " - " . $exp['end_date'] . ")";
        if ($exp['description']) {
            $content .= " - " . nl2br($exp['description']);
        }
        if ($exp['achievements']) {
            $content .= " - Achievements: " . nl2br($exp['achievements']);
        }
        if ($exp['location']) {
            $content .= " - Location: " . $exp['location'];
        }
        $content .= "</li>";
    }
    $content .= "</ul></div>";

    // Add Skills Section
    $content .= "<div style='margin-top: 20px;'><h2 style='font-size: 24px;'>Skills</h2><ul>";
    foreach ($skills as $skill) {
        $content .= "<li style='font-size: 16px;'>" . $skill['skill_name'] . " - " . $skill['proficiency'] . " (" . $skill['years_experience'] . " years)</li>";
    }
    $content .= "</ul></div>";
    // Add Projects Section
    $content .= "<div style='margin-top: 20px;'><h2 style='font-size: 24px;'>Projects</h2><ul>";
    foreach ($projects as $proj) {
        $content .= "<li style='font-size: 16px;'>" . $proj['title'] . " - " . nl2br($proj['description']);
        if ($proj['technologies']) {
            $content .= " - Technologies: " . $proj['technologies'];
        }
        if ($proj['url']) {
            $content .= " - URL: <a href='" . $proj['url'] . "'>Project Link</a>";
        }
        $content .= "</li>";
    }
    $content .= "</ul></div>";

    $content .= "</div>";

    return $content;
}
?>
