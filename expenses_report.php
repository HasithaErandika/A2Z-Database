<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection details from session
$servername = "localhost";
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$password = isset($_SESSION['password']) ? $_SESSION['password'] : '';
$dbname = isset($_SESSION['dbname']) ? $_SESSION['dbname'] : '';
$table = isset($_GET['table']) ? $_GET['table'] : '';

// Check if session variables are set
if (empty($username) || empty($password) || empty($dbname)) {
    die("Database connection details are not set in session.");
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Variables to store the totals
$total_expenses = 0;
$total_invoices = 0;
$employee_wages = 0;
$total_amount = 0;
$expenses_by_category = [
    'Meals' => 0,
    'Tools' => 0,
    'Fuel' => 0,
    'Materials' => 0,
    'Hiring of labor' => 0,
    'Other' => 0
];
$employee_payments_by_type = [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Calculate total expenses
    $sql_expenses = "SELECT SUM(Exp_amount) AS total_expenses, Expenses_Category FROM Operational_Expenses WHERE Expensed_Date BETWEEN ? AND ? GROUP BY Expenses_Category";
    $stmt_expenses = $conn->prepare($sql_expenses);
    if ($stmt_expenses === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt_expenses->bind_param("ss", $start_date, $end_date);
    $stmt_expenses->execute();
    $result_expenses = $stmt_expenses->get_result();
    if ($result_expenses === false) {
        die("Error executing statement: " . $stmt_expenses->error);
    }
    while ($row = $result_expenses->fetch_assoc()) {
        $expenses_by_category[$row['Expenses_Category']] = $row['total_expenses'];
        $total_expenses += $row['total_expenses'];
    }
    $stmt_expenses->close();

    // Calculate total invoices
    $sql_invoices = "SELECT SUM(Invoice_Value) AS total_invoices FROM Invoice_Data WHERE Invoice_Date BETWEEN ? AND ?";
    $stmt_invoices = $conn->prepare($sql_invoices);
    if ($stmt_invoices === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt_invoices->bind_param("ss", $start_date, $end_date);
    $stmt_invoices->execute();
    $result_invoices = $stmt_invoices->get_result();
    if ($result_invoices === false) {
        die("Error executing statement: " . $stmt_invoices->error);
    }
    if ($row = $result_invoices->fetch_assoc()) {
        $total_invoices = $row['total_invoices'];
    }
    $stmt_invoices->close();

    // Calculate employee payments
    $sql_employee_payments = "SELECT SUM(Paid_Amount) AS total_amount, Payment_Type FROM Employee_Payments WHERE Payment_Date BETWEEN ? AND ? GROUP BY Payment_Type";
    $stmt_employee_payments = $conn->prepare($sql_employee_payments);
    if ($stmt_employee_payments === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt_employee_payments->bind_param("ss", $start_date, $end_date);
    $stmt_employee_payments->execute();
    $result_employee_payments = $stmt_employee_payments->get_result();
    if ($result_employee_payments === false) {
        die("Error executing statement: " . $stmt_employee_payments->error);
    }

    // Initialize array to store payments by type
    $employee_payments_by_type = [];

    while ($row = $result_employee_payments->fetch_assoc()) {
        // Store total amount by payment type
        $employee_payments_by_type[$row['Payment_Type']] = $row['total_amount'];

        // Accumulate total amount
        $total_amount += $row['total_amount'];
    }

    // Close statement
    $stmt_employee_payments->close();

    // Calculate profit
    $profit = $total_invoices - ($total_expenses + $total_amount);
}

$conn->close();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Expenses Report</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqk1w27APbCZZp+trN3v8TpgAm16FB46Z+9xjbBJCGSdOdQoNLwOp8aAgBxSsQfjJxFoq6+A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mt-5">Generate Expenses Report</h2>
            <a href="tables.php" class="btn btn-goback">Go Back</a>
        </div>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="mt-3">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </form>

        <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
            <h2 class="mt-5">Report</h2>
            <table class="table table-bordered mt-3">
                <thead class="thead-dark">
                    <tr>
                        <th>Total Expenses</th>
                        <th>Total Invoices</th>
                        <th>Employee Wages</th>
                        <th>Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo number_format($total_expenses, 2); ?></td>
                        <td><?php echo number_format($total_invoices, 2); ?></td>
                        <td><?php echo number_format($total_amount, 2); ?></td>
                        <td><?php echo number_format($profit, 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 class="mt-5">Expenses by Category</h3>
            <table class="table table-bordered mt-3">
                <thead class="thead-light">
                    <tr>
                        <th>Category</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses_by_category as $category => $amount): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category); ?></td>
                            <td><?php echo number_format($amount, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 class="mt-5">Employee Payments by Type</h3>
            <table class="table table-bordered mt-3">
                <thead class="thead-light">
                    <tr>
                        <th>Payment Type</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employee_payments_by_type as $payment_type => $amount): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment_type); ?></td>
                            <td><?php echo number_format($amount, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 class="mt-5">Financial Summary Chart</h3>
            <div style="display: flex;">
                <canvas id="financialChart" width="400" height="200"></canvas>
                <div class="legend-container">
                    <h4>Legend</h4>
                    <table class="legend-table">
                        <!--<thead>-->
                        <!--    <tr>-->
                        <!--        <th>Color</th>-->
                        <!--        <th>Label</th>-->
                        <!--        <th>Percentage</th>-->
                        <!--    </tr>-->
                        <!--</thead>-->
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                const ctx = document.getElementById('financialChart').getContext('2d');
                const financialChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Total Expenses', 'Total Invoices', 'Employee Wages', 'Profit'],
                        datasets: [{
                            data: [
                                <?= $total_expenses ?>,
                                <?= $total_invoices ?>,
                                <?= $total_amount ?>,
                                <?= $profit ?>
                            ],
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.2)',
                                'rgba(54, 162, 235, 0.2)',
                                'rgba(255, 206, 86, 0.2)',
                                'rgba(153, 102, 255, 0.2)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(153, 102, 255, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Financial Summary'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (tooltipItem) {
                                        let dataLabel = tooltipItem.label;
                                        let value = tooltipItem.raw;

                                        let total = tooltipItem.dataset.data.reduce((acc, val) => acc + val, 0);
                                        let percentage = ((value / total) * 100).toFixed(2);

                                        return `${dataLabel}: ${value.toLocaleString()} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    },
                    // After the chart is rendered, generate the legend table
                    plugins: [{
                        afterRender: function (chart) {
                            const legendContainer = document.querySelector('.legend-container');
                            const legendTable = document.createElement('table');
                            legendTable.className = 'legend-table';
                            legendTable.innerHTML = `
                        <thead>
                            <tr>
                                <th>Color</th>
                                <th>Label</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${chart.data.labels.map((label, index) => `
                                <tr>
                                    <td><div style="width: 20px; height: 20px; background-color: ${chart.data.datasets[0].backgroundColor[index]}"></div></td>
                                    <td>${label}</td>
                                    <td>${((chart.data.datasets[0].data[index] / chart.data.datasets[0].data.reduce((acc, val) => acc + val, 0)) * 100).toFixed(2)}%</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    `;
                            legendContainer.appendChild(legendTable);
                        }
                    }]
                });
            </script>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>
