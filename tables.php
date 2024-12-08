<?php
// session_check.php
session_start();
session_regenerate_id(true); 

if (!isset($_SESSION['username']) || empty($_SESSION['username']) || 
    !isset($_SESSION['dbname']) || empty($_SESSION['dbname'])) {
    header("Location: index.php");
    exit();
} 
?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet"> 
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
</head>

<body>
    <?php include 'header.php'; ?> <!— Include header -->


    <div class="container main-content text-center">
        <h2 class="mt-5 mb-4">Operational Data</h2>
        <div class="row">
            <div class="col-md-3">
                <a href="manage_table.php?table=Attendance" class="btn btn-custom mb-3">
                    <i class="fas fa-calendar-check"></i> <br> Attendance
                </a>
            </div>
            <div class="col-md-3">
                <a href="manage_table.php?table=Employee" class="btn btn-custom mb-3">
                    <i class="fas fa-user-tie"></i> <br> Employee
                </a>
            </div>
            <div class="col-md-3">
                <a href="manage_table.php?table=Employee_Bank_Details" class="btn btn-custom mb-3">
                    <i class="fas fa-money-check-alt"></i> <br> Employee Bank Details
                </a>
            </div>
            <div class="col-md-3">
                <a href="manage_table.php?table=Projects" class="btn btn-custom mb-3">
                    <i class="fas fa-project-diagram"></i> <br> Projects
                </a>
            </div>
            <div class="col-md-3">
                <a href="manage_table.php?table=Jobs" class="btn btn-custom mb-3">
                    <i class="fas fa-briefcase"></i> <br> Jobs
                </a>
            </div>
            <div class="col-md-3">
                <a href="manage_table.php?table=Operational_Expenses" class="btn btn-custom mb-3">
                    <i class="fas fa-receipt"></i> <br> Operational Expenses
                </a>
            </div>
            <div class="col-md-3">
                <a href="manage_table.php?table=Invoice_Data" class="btn btn-custom mb-3">
                    <i class="fas fa-file-invoice"></i> <br> Invoice Data
                </a>
            </div>
            <div class="col-md-3">
                <a href="manage_table.php?table=Employee_Payments" class="btn btn-custom mb-3">
                    <i class="fas fa-money-check-alt"></i> <br> Employee Payments
                </a>
            </div>
            <div class="col-md-3">
                <a href="manage_table.php?table=Salary_Increments" class="btn btn-custom mb-3">
                    <i class="fas fa-money-check-alt"></i> <br> Salary Increments
                </a>
            </div>
            
        </div>
    </div>
    <div class="container main-content text-center">
        <h2 class="mt-5 mb-4">Reports</h2>
        <div class="row">
            <div class="col-md-3">
                <a href="wages_report.php" class="btn btn-custom mb-3">
                    <i class="fas fa-money-bill"></i> <br> Monthly Wages
                </a>
            </div>
            <div class="col-md-3">
                <a href="expenses_report.php" class="btn btn-custom mb-3">
                    <i class="fas fa-file-invoice-dollar"></i> <br> Expenses Report
                </a>
            </div>
            <div class="col-md-3">
                <a href="cost_calculation.php?table=Jobs" class="btn btn-custom mb-3">
                    <i class="fas fa-chart-pie"></i> <br> Cost Calculation
                </a>
            </div>
            <div class="col-md-3">
                <a href="finance_report.php" class="btn btn-custom mb-3">
                    <i class="fas fa-chart-line"></i> <br> Finance Report
                </a>
            </div>
        </div>
    </div>

     <?php include 'footer.php'; ?> <!— Include footer -->

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>
