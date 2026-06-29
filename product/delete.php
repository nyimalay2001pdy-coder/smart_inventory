<?php

include "../config/db.php";


// Get Product ID

$id = $_GET['id'];




// Get Product Image Before Delete

$sql = "

SELECT image 

FROM products

WHERE id='$id'

";


$result = mysqli_query($conn, $sql);


$product = mysqli_fetch_assoc($result);





// Delete Image File


if ($product['image'] != "") {


    $image_path = "../assets/images/" . $product['image'];



    if (file_exists($image_path)) {


        unlink($image_path);
    }
}





// Delete Product From Database


$delete = "

DELETE FROM products

WHERE id='$id'

";



$result = mysqli_query($conn, $delete);





if ($result) {


    header("Location:index.php");

    exit;
} else {


    echo "Delete Failed";
}
