<?php
// 1. Database Connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "migs_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 2. Setup Pagination Variables
$limit = 100; // Members per page
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 3. Get Search and Filter Inputs
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$branch_filter = isset($_GET['branch_id']) ? mysqli_real_escape_string($conn, $_GET['branch_id']) : '';

// 4. Build the Dynamic Query
$where_clauses = ["1=1"];

if ($search !== '') {
    $where_clauses[] = "full_name LIKE '%$search%'";
}

if ($branch_filter !== '') {
    $where_clauses[] = "branch_id = '$branch_filter'";
}

$where_sql = implode(" AND ", $where_clauses);

// 5. Get Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM members WHERE $where_sql";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// 6. Fetch the Data
$main_query = "SELECT * FROM members WHERE $where_sql ORDER BY full_name ASC LIMIT $offset, $limit";
$result = mysqli_query($conn, $main_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Member Finder</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f9f9f9; }
        .filter-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f2f2f2; }
        .pagination { margin-top: 20px; }
        .pagination a { padding: 8px 16px; border: 1px solid #ddd; text-decoration: none; color: #007bff; margin-right: 5px; border-radius: 4px; }
        .pagination a.active { background: #007bff; color: white; border-color: #007bff; }
    </style>
</head>
<body>

    <h2>Member Directory (Total: <?php echo number_format($total_rows); ?>)</h2>

    <div class="filter-box">
        <form method="GET" action="find.php">
            <input type="text" name="search" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 8px; width: 250px;">
            
            <select name="branch_id" style="padding: 8px;">
                <option value="">-- All Branches --</option>
                <?php
                $branches = [
                    19=>"Panabo", 20=>"Tibungco", 21=>"Bajada", 22=>"Matina", 
                    23=>"Tagum", 24=>"Sto. Tomas", 25=>"Toril", 26=>"Surigao", 
                    27=>"CDO", 28=>"Valencia", 29=>"Gensan", 30=>"Koronadal", 
                    31=>"Butuan", 32=>"Digos", 33=>"Kidapawan", 34=>"Calinan", 35=>"SCWE"
                ];
                foreach ($branches as $id => $name) {
                    $selected = ($branch_filter == $id) ? 'selected' : '';
                    echo "<option value='$id' $selected>$name</option>";
                }
                ?>
            </select>
            
            <button type="submit" style="padding: 8px 15px; cursor: pointer;">Search</button>
            <a href="find.php" style="font-size: 13px; margin-left: 10px;">Clear Filters</a>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Branch Name</th>
                <th>Branch ID</th>
                <th>MIGS Category</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $row['full_name']; ?></td>
                        <td><?php echo $row['branch_name']; ?></td>
                        <td><?php echo $row['branch_id']; ?></td>
                        <td><?php echo $row['migs_category']; ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No members found matching your search.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php if($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>&branch_id=<?php echo $branch_filter; ?>">« Previous</a>
        <?php endif; ?>

        <?php 
        // Show 5 pages around the current page
        for($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&branch_id=<?php echo $branch_filter; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>&branch_id=<?php echo $branch_filter; ?>">Next »</a>
        <?php endif; ?>
    </div>

</body>
</html>