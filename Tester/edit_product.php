<?php
include 'db.php';

$id = $_GET['id'];

// Fetch existing record
$sql = "SELECT * FROM products WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
?>
<form method="POST">
    <input type="text" name="product_id" value="<?= $data['product_id'] ?>" required>
    <input type="text" name="product_type" value="<?= $data['product_type'] ?>" required>
    <input type="text" name="revision" value="<?= $data['revision'] ?>" required>
    <button type="submit" name="update_product">Update</button>
</form>

<?php
if(isset($_POST['update_product'])){

    $product_id   = $_POST['product_id'];
    $product_type = $_POST['product_type'];
    $revision     = $_POST['revision'];

    $update = "UPDATE products 
               SET product_id=?, product_type=?, revision=? 
               WHERE id=?";

    $stmt2 = $conn->prepare($update);
    $stmt2->bind_param("sssi", $product_id, $product_type, $revision, $id);

    if($stmt2->execute()){
        echo "Updated!";
    } else {
        echo "Error: ".$conn->error;
    }
}
?>
