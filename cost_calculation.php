<?php
session_start();

// Regenerate session ID to prevent session fixation attacks
session_regenerate_id(true);

// Check if session variables are set
if (!isset($_SESSION['username']) || !isset($_SESSION['password']) || !isset($_SESSION['dbname']) || !isset($_GET['table'])) {
    header("Location: index.php");
    exit();
}

// Retrieve and sanitize session variables
$servername = "localhost";
$username = htmlspecialchars($_SESSION['username']);
$password = htmlspecialchars($_SESSION['password']);
$dbname = htmlspecialchars($_SESSION['dbname']);
$table = htmlspecialchars($_GET['table']);

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL query to fetch job details, operational expenses, attendance, and invoice data
$sql = "
    SELECT 
        Jobs.Job_ID, 
        Jobs.Service_Category, 
        Jobs.Date_completed, 
        Jobs.Customer_ref, 
        Jobs.Location, 
        Jobs.Job_capacity,
        COALESCE(Summary.Expense_Summary, 'No expenses') AS Expense_Summary,
        IFNULL(SUM(Employee.Daily_Wage * Attendance.Presence), 0) AS Total_Salary,
        GROUP_CONCAT(CONCAT(Employee.Emp_ID, ': ', Employee.Daily_Wage * Attendance.Presence) SEPARATOR ', ') AS Employee_Details,
        Invoice_Data.Invoice_No,
        Invoice_Data.Invoice_Value
    FROM 
        Jobs
    LEFT JOIN (
        SELECT 
            Job_ID,
            GROUP_CONCAT(CONCAT(Expenses_Category, ': ', Total_Expenses) SEPARATOR ', ') AS Expense_Summary
        FROM (
            SELECT 
                Job_ID,
                Expenses_Category,
                SUM(Exp_amount) AS Total_Expenses
            FROM 
                Operational_Expenses
            GROUP BY 
                Job_ID, Expenses_Category
        ) AS Expenses
        GROUP BY 
            Job_ID
    ) AS Summary ON Jobs.Job_ID = Summary.Job_ID
    LEFT JOIN 
        Attendance ON Jobs.Job_ID = Attendance.Job_ID
    LEFT JOIN 
        Employee ON Attendance.Emp_ID = Employee.Emp_ID
    LEFT JOIN 
        Invoice_Data ON Jobs.Job_ID = Invoice_Data.Job_ID
    WHERE 
        Jobs.Job_ID <> 1  -- Exclude Job_ID = 1
    GROUP BY 
        Jobs.Job_ID, 
        Jobs.Service_Category, 
        Jobs.Date_completed, 
        Jobs.Customer_ref, 
        Jobs.Location, 
        Jobs.Job_capacity, 
        Invoice_Data.Invoice_No, 
        Invoice_Data.Invoice_Value";

$result = $conn->query($sql);

// Check for SQL query error
if (!$result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost Calculation</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqk1w27APbCZZp+trN3v8TpgAm16FB46Z+9xjbBJCGSdOdQoNLwOp8aAgBxSsQfjJxFoq6+A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="header mt-4 mb-4">
            <h2>Cost Calculation Summary for Jobs</h2>
            <a href="tables.php" class="btn btn-goback">Go Back</a>
        </div>
        <?php
        if ($result->num_rows > 0) {
            echo "<div class='table-responsive'>
                    <table class='table table-bordered table-hover'>
                        <thead class='thead-dark'>
                            <tr>
                                <th>Job ID</th>
                                <th>Service Category</th>
                                <th>Date Completed</th>
                                <th>Customer Ref</th>
                                <th>Location</th>
                                <th>Job Capacity</th>
                                <th>Invoice No</th>
                                <th class='expense-summary-col'>Operational Expenses Summary</th>
                                <th class='employee-details-col'>Employee Details (Emp ID: Contribution)</th>
                                <th>Total Salary</th>
                                <th>Invoice Value</th>
                                <th>Net Profit</th>
                            </tr>
                        </thead>
                        <tbody>";
            while ($row = $result->fetch_assoc()) {
                // Calculate operational expenses total
                $operationalExpensesTotal = 0;
                if ($row['Expense_Summary'] !== 'No expenses') {
                    $expenseDetails = explode(', ', $row['Expense_Summary']);
                    foreach ($expenseDetails as $expense) {
                        $parts = explode(': ', $expense);
                        $operationalExpensesTotal += floatval($parts[1]);
                    }
                }

                // Calculate net profit
                $netProfit = $row['Invoice_Value'] - ($row['Total_Salary'] + $operationalExpensesTotal);

                // Sum contributions by Emp_ID
                $employeeDetails = [];
                if ($row['Employee_Details'] !== null) {
                    $employeeDetailsArr = explode(', ', $row['Employee_Details']);
                    foreach ($employeeDetailsArr as $detail) {
                        list($empId, $contribution) = explode(': ', $detail);
                        if (!isset($employeeDetails[$empId])) {
                            $employeeDetails[$empId] = 0;
                        }
                        $employeeDetails[$empId] += floatval($contribution);
                    }
                }

                // Format employee details for display
                $formattedEmployeeDetails = [];
                foreach ($employeeDetails as $empId => $totalContribution) {
                    $formattedEmployeeDetails[] = "{$empId}: " . number_format($totalContribution, 2);
                }
                $formattedEmployeeDetailsStr = implode(', ', $formattedEmployeeDetails);

                // Append total expenses to Expense_Summary
                $expenseSummaryWithTotal = $row['Expense_Summary'] . ', <span class="total-label">Total:</span> ' . number_format($operationalExpensesTotal, 2);

                // Determine the color for net profit
                $netProfitColor = $netProfit < 0 ? 'red' : 'green';

                echo "<tr>
                        <td>{$row['Job_ID']}</td>
                        <td>{$row['Service_Category']}</td>
                        <td>{$row['Date_completed']}</td>
                        <td>{$row['Customer_ref']}</td>
                        <td>{$row['Location']}</td>
                        <td>{$row['Job_capacity']}</td>
                        <td>{$row['Invoice_No']}</td>
                        <td class='expense-summary-col'>{$expenseSummaryWithTotal}</td>
                        <td class='employee-details-col'>{$formattedEmployeeDetailsStr}</td>
                        <td>" . number_format($row['Total_Salary'], 2) . "</td>
                        <td>" . number_format($row['Invoice_Value'], 2) . "</td>
                        <td class='net-profit' style='color: {$netProfitColor};'>" . number_format($netProfit, 2) . "</td>
                      </tr>";
            }
            echo "</tbody></table></div>";
        } else {
            echo "<p class='text-center'>No job data found.</p>";

            // Additional debug information
            $debug_sql = "SELECT COUNT(*) as job_count FROM Jobs";
            $debug_result = $conn->query($debug_sql);
            if ($debug_result) {
                $debug_row = $debug_result->fetch_assoc();
                echo "<p class='text-center'>Number of jobs in Jobs table: {$debug_row['job_count']}</p>";
            } else {
                echo "<p class='text-center'>Error fetching job count: " . $conn->error . "</p>";
            }
        }
        $conn->close();
        ?>
    </div>
    <?php include 'footer.php'; ?> <!-- Include footer -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
