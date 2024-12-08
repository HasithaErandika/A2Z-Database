<?php
session_start();

// Function to get employee name from Emp_ID (Improved error handling)
function getEmployeeName($empId, $conn) {
    $sql = "SELECT Emp_Name FROM Employee WHERE Emp_ID = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error . " (Query: $sql)");
        return 'Error'; 
    }

    $stmt->bind_param("i", $empId);

    if (!$stmt->execute()) {
        error_log("Error executing statement: " . $stmt->error . " (Query: $sql)");
        return 'Error'; 
    }

    $stmt->bind_result($empName);
    $stmt->fetch();
    $stmt->close();
    return $empName ? htmlspecialchars($empName) : 'Unknown';
}

// Function to get boolean icon
function getBooleanIcon($value) {
    return $value ? '<i class="fas fa-check green"></i>' : '<i class="fas fa-times red"></i>';
}

// Function to get Service_Category and Customer_ref from Jobs table 
function getJobDetails($jobId, $conn) {
    $sql = "SELECT Service_Category, Customer_ref FROM Jobs WHERE Job_ID = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error . " (Query: $sql)");
        return 'Error - Error retrieving job details'; 
    }

    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $stmt->bind_result($serviceCategory, $customerRef);
    $stmt->fetch();
    $stmt->close();

    $serviceCategory = $serviceCategory ? htmlspecialchars($serviceCategory) : '';
    $customerRef = $customerRef ? htmlspecialchars($customerRef) : '';

    return "{$serviceCategory} - {$customerRef}";
}

// Function to check if an invoice exists for a given Job_ID
function checkInvoiceExists($jobId, $conn) {
    $sql = "SELECT COUNT(*) FROM Invoice_Data WHERE Job_ID = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error . " (Query: $sql)");
        return false; // Assume no invoice if error occurs
    }

    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

if (!isset($_SESSION['username']) || !isset($_SESSION['password']) || !isset($_SESSION['dbname']) || !isset($_GET['table'])) {
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

// Sanitize and get table name
$table = mysqli_real_escape_string($conn, $_GET['table']);

// Get primary key of the table
$primaryKeyResult = $conn->query("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");
if ($primaryKeyResult->num_rows > 0) {
    $primaryKeyRow = $primaryKeyResult->fetch_assoc();
    $primaryKey = $primaryKeyRow['Column_name'];
} else {
    die("Error: Could not determine the primary key for the table $table.");
}

// Handle job_id filtering for Attendance table
if ($table === 'Attendance' && isset($_GET['job_id'])) {
    $jobId = (int)$_GET['job_id'];
    $sql = "SELECT * FROM $table WHERE Job_ID = $jobId ORDER BY $primaryKey DESC";
} else {
    $sql = "SELECT * FROM $table ORDER BY $primaryKey DESC";
}

$result = $conn->query($sql);
if (!$result) {
    die("Error retrieving data from the table: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Table - <?php echo htmlspecialchars($table); ?></title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> 
</head>
<body>
    <div class="container-fluid mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Table: <?php echo htmlspecialchars($table); ?></h2>
            <a href="tables.php" class="btn btn-primary">Go Back</a>
        </div>

        <div class="mb-3">
            <form id="dateRangeForm" method="GET" action="download_csv.php">
                <input type="hidden" name="table" value="<?php echo htmlspecialchars($table); ?>">
                <div class="form-row">
                    <div class="col">
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="col">
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                    <div class="col">
                        <button type="submit" class="btn btn-info">Download CSV</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($table === 'Jobs') : ?>
            <div class="mb-3">
                <button class="btn btn-info filter-button" data-filter="Telecommunication Services">Telecommunication Services</button>
                <button class="btn btn-info filter-button" data-filter="Civil Installations">Civil Installations</button>
                <button class="btn btn-info filter-button" data-filter="Electrical Installations">Electrical Installations</button>
                <button class="btn btn-info filter-button" data-filter="Solar PV Systems">Solar PV Systems</button>
                <button class="btn btn-info filter-button" data-filter="AC Maintenance and Installation">AC Maintenance and Installation</button>
                <button class="btn btn-info filter-button" data-filter="Other">Other</button>
            </div>
        <?php endif; ?>

        <div class="search-box">
            <input class="form-control" id="searchInput" type="text" placeholder="Search..">
            <a href="add_entry.php?table=<?php echo htmlspecialchars($table); ?>" class="btn btn-success"><i class="fas fa-plus"></i> Add Entry</a>
        </div>

        <div class="table-wrapper">
            <table class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <?php
                        if ($result->num_rows > 0) {
                            $fields = $result->fetch_fields();
                            foreach ($fields as $field) {
                                echo "<th>" . htmlspecialchars($field->name) . "</th>";
                            }
                            echo "<th>Actions</th>"; 
                        } else {
                            echo "<tr><th>No entries found in the table.</th></tr>";
                        }
                        ?>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            foreach ($row as $columnName => $data) {
                                echo "<td>";
                                if ($columnName == 'Job_ID' && in_array($table, ['Attendance', 'Operational_Expenses', 'Invoice_Data'])) {
                                    echo getJobDetails($data, $conn);
                                } elseif ($columnName == 'Emp_ID' && $table != 'Employee') {
                                    echo getEmployeeName($data, $conn);
                                } elseif ($table == 'Attendance' && $columnName == 'Presence') {
                                    if ($data == 1.0) {
                                        echo '<span class="green">Full Day</span>';
                                    } elseif ($data == 0.5) {
                                        echo '<span class="yellow">Half Day</span>';
                                    } else {
                                        echo '<span class="red">Not Attended</span>';
                                    }
                                } else {
                                    echo !empty($data) ? htmlspecialchars($data) : '';
                                }
                                echo "</td>";
                            }

                            // Actions column
                            echo '<td>';

                            if ($table === 'Jobs') {
                                if (checkInvoiceExists($row['Job_ID'], $conn)) {
                                    echo '<a href="view_invoice.php?job_id=' . htmlspecialchars($row['Job_ID']) . '" class="btn btn-invoice btn-sm">Invoice</a>';
                                } else {
                                    echo '<a href="add_entry.php?table=Invoice_Data&job_id=' . htmlspecialchars($row['Job_ID']) . '" class="btn btn-invoice-yet-to-add btn-sm">Yet to Add</a>'; 
                                }
                                
                                echo '<a href="manage_table.php?table=Attendance&job_id=' . htmlspecialchars($row['Job_ID']) . '" class="btn btn-primary btn-sm">Attendance</a>';
                            }

                            // Update and Delete buttons using primary key
                            echo '<a href="update_entry.php?table=' . htmlspecialchars($table) . '&id=' . htmlspecialchars($row[$primaryKey]) . '" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> </a>'; 
                            echo '<a href="delete_entry.php?table=' . htmlspecialchars($table) . '&id=' . htmlspecialchars($row[$primaryKey]) . '" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash-alt"></i> </a>';

                            echo '</td>'; 
                            echo "</tr>";
                        } 
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div> 

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#searchInput').keyup(function () {
                var input = $(this).val().toLowerCase();
                $('#tableBody tr').filter(function () {
                    $(this).toggle($(this).text().toLowerCase().indexOf(input) > -1);
                });
            });

            $('.filter-button').click(function () {
                var filter = $(this).data('filter').toLowerCase();
                $('#tableBody tr').filter(function () {
                    $(this).toggle($(this).text().toLowerCase().indexOf(filter) > -1);
                });
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>
