<?php
session_start();

// Check if session variables are set
if (!isset($_SESSION['username'], $_SESSION['password'], $_SESSION['dbname'])) {
    header("Location: index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = $_SESSION['username'];
$password = $_SESSION['password'];
$dbname = $_SESSION['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch employees excluding resigned employees
$sql = "SELECT Emp_ID, Emp_Name, Daily_Wage, Basic_Salary FROM Employee WHERE Date_of_resigned IS NULL";
$employees_result = $conn->query($sql);
$employees = $employees_result->fetch_all(MYSQLI_ASSOC);

// Function to calculate employee payments
function calculate_employee_payments($conn, $start_date, $end_date) {
    $sql = "SELECT Emp_ID, SUM(Paid_Amount) AS total_amount
            FROM Employee_Payments
            WHERE Payment_Date BETWEEN ? AND ?
            GROUP BY Emp_ID";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[$row['Emp_ID']] = $row['total_amount'];
    }
    return $payments;
}

// Function to calculate presence for all employees
function calculate_presence_data($conn, $start_date, $end_date) {
    $presence_data = [];
    $sql = "SELECT Emp_ID, SUM(CASE WHEN Presence = 1 THEN 1 WHEN Presence = 0.5 THEN 0.5 ELSE 0 END) AS presence_count
            FROM Attendance
            WHERE Atd_Date BETWEEN ? AND ?
            GROUP BY Emp_ID";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $presence_data[$row['Emp_ID']] = $row['presence_count'];
    }
    return $presence_data;
}


// Function to get latest increment for a specified type and check if it's within the range
function get_latest_increment($conn, $emp_id, $type, $start_date, $end_date) {
    // Get the latest increment of the specified type
    $sql = "SELECT Increment_Amount, New_Salary, Increment_Date
            FROM Salary_Increments
            WHERE Emp_ID = ? AND Type = ?
            ORDER BY Increment_Date DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $emp_id, $type);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Check if the Increment Date is before or within the Start Date
        $increment_date = $row['Increment_Date'];
        if (strtotime($increment_date) <= strtotime($end_date)) {
            // Use increment if it's before or within the end date
            return $row;
        }
    }
    return null; // No valid increment found within the range
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $report_type = $_POST['report_type'] ?? '';

    $ETF_PERCENTAGE = 0.03;
    $EPF_EMPLOYEE_PERCENTAGE = 0.08;
    $EPF_COMPANY_PERCENTAGE = 0.12;

    $report_output = ''; // To store generated report

    if ($report_type && strtotime($start_date) && strtotime($end_date)) {
        if (strtotime($start_date) > strtotime($end_date)) {
            $report_output = '<div class="alert alert-danger mt-4">End date must be later than start date.</div>';
        } else {
            $employee_payments = calculate_employee_payments($conn, $start_date, $end_date);
            $presence_data = calculate_presence_data($conn, $start_date, $end_date);

            if ($report_type == 'summary') {
                // Generate summary report for all employees
                $report_output = '
                    <h4 class="mt-4">Summary Report</h4>
                    <div class="alert alert-info">
                        <h5>EPF and ETF Percentages</h5>
                        <ul>
                            <li><b>ETF:</b> 3%</li>
                            <li><b>EPF (Employee):</b> 8%</li>
                            <li><b>EPF (Company):</b> 12%</li>
                        </ul>
                    </div>
                    <table class="table table-bordered mt-2">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Presence Count</th>
                                <th>Daily Wage</th>
                                <th>Basic Salary</th>
                                <th>ETF</th>
                                <th>EPF (Employee)</th>
                                <th>EPF (Company)</th>
                                <th>Total Payable</th>
                                <th>Paid Amount</th>
                                <th>Net Payable</th>
                            </tr>
                        </thead>
                        <tbody>';

                foreach ($employees as $employee) {
                    $employee_id = $employee['Emp_ID'];
                    $employee_name = htmlspecialchars($employee['Emp_Name']);

                    // Adjust Daily Wage and Basic Salary based on increments
                    $daily_wage_increment = get_latest_increment($conn, $employee_id, 'Daily Wage', $start_date, $end_date);
                    $basic_salary_increment = get_latest_increment($conn, $employee_id, 'Basic Salary', $start_date, $end_date);

                    // If there's an increment for Daily Wage, use the new salary
                    $daily_wage = $daily_wage_increment['New_Salary'] ?? $employee['Daily_Wage'];
                    // If there's an increment for Basic Salary, use the new salary
                    $basic_salary = $basic_salary_increment['New_Salary'] ?? $employee['Basic_Salary'];

                    $presence_count = $presence_data[$employee_id] ?? 0;
                    $total_payable = $presence_count * $daily_wage;

                    // Calculating ETF, EPF Contributions
                    $etf = $basic_salary * $ETF_PERCENTAGE;
                    $epf_employee = $basic_salary * $EPF_EMPLOYEE_PERCENTAGE;
                    $epf_company = $basic_salary * $EPF_COMPANY_PERCENTAGE;

                    $paid_amount = $employee_payments[$employee_id] ?? 0;
                    $net_payable = $total_payable - $paid_amount - $epf_employee;

                    $report_output .= "
                        <tr>
                            <td>{$employee_name}</td>
                            <td>" . htmlspecialchars($presence_count) . "</td>
                            <td>" . number_format($daily_wage, 2) . "</td>
                            <td>" . number_format($basic_salary, 2) . "</td>
                            <td>" . number_format($etf, 2) . "</td>
                            <td>" . number_format($epf_employee, 2) . "</td>
                            <td>" . number_format($epf_company, 2) . "</td>
                            <td>" . number_format($total_payable, 2) . "</td>
                            <td>" . number_format($paid_amount, 2) . "</td>
                            <td>" . number_format($net_payable, 2) . "</td>
                        </tr>";
                }

                $report_output .= '</tbody></table>';
            }
        }
    } else {
        $report_output = '<div class="alert alert-danger mt-4">Please provide valid inputs.</div>';
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Wages Report</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Monthly Wages Report</h2>
    <a href="tables.php" class="btn btn-primary btn-goback mb-4">Go Back</a>

    <div class="card">
        <div class="card-body">
            <form method="post" action="">
                <div class="form-group">
                    <label for="report_type">Report Type:</label>
                    <div>
                        <input type="radio" id="summary" name="report_type" value="summary" required>
                        <label for="summary">All Employees Summary</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                </div>
                <button type="submit" name="generate_report" class="btn btn-primary">Generate Report</button>
            </form>
        </div>
    </div>

    <div id="report-output" class="mt-5">
        <?= isset($report_output) ? $report_output : '' ?>
    </div>
</div>
</body>
</html>
