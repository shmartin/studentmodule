<?php // for live search
include 'dbcon.php';
$search = $_GET['query'] ?? '';

$sql = "SELECT d.did, d.dtitle, u.firstname AS author_firstname, u.lastname AS author_lastname, 
        dd.dadviser, p.description, 
        COALESCE(GROUP_CONCAT(CONCAT(us.firstname, ' ', us.lastname) SEPARATOR ', '), 'No reviewers assigned') AS reviewers
        FROM document d  
        JOIN document_details dd ON d.did = dd.did  
        JOIN users u ON dd.dauthor = u.uid  
        JOIN program p ON dd.program = p.pid  
        LEFT JOIN document_reviewers dr ON d.did = dr.did  
        LEFT JOIN users us ON dr.reviewer_id = us.uid
        WHERE d.dtitle LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR dd.dadviser LIKE ? OR p.description LIKE ?
        GROUP BY d.did, d.dtitle, author_firstname, author_lastname, dd.dadviser, p.description";

$param = "%{$search}%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $param, $param, $param, $param, $param);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  echo "<tr>
    <td>{$row['dtitle']}</td>
    <td>{$row['author_firstname']} {$row['author_lastname']}</td>
    <td>{$row['dadviser']}</td>
    <td>{$row['description']}</td>
    <td>{$row['reviewers']}</td>
    <td><button class='btn btn-main btn-sm' data-bs-toggle='modal' data-bs-target='#assignModal' 
                data-did='{$row['did']}'>Assign Reviewer</button></td>
  </tr>";
}
?>
