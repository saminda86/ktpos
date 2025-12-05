<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Product List | Inventory System</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <style>
    /* --- Main Styles (Screenshot එකේ විදිහටම) --- */
    body {
      font-family: 'Source Sans Pro', 'Segoe UI', sans-serif;
      background-color: #f4f6f9;
      margin: 0;
      padding: 20px;
    }

    .box {
      background: #fff;
      border-top: 3px solid #d2d6de;
      border-radius: 3px;
      box-shadow: 0 1px 1px rgba(0,0,0,0.1);
      padding: 10px;
    }

    /* Table Design */
    .table-responsive {
      width: 100%;
      overflow-x: auto; /* Laptop වල Scroll වෙන්න */
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1000px;
      font-size: 14px;
    }

    th {
      background-color: #f9f9f9;
      border-bottom: 2px solid #f4f4f4;
      padding: 10px;
      text-align: left;
      color: #333;
      font-weight: bold;
    }

    td {
      padding: 10px;
      border-bottom: 1px solid #f4f4f4;
      vertical-align: middle;
      color: #333;
    }

    /* --- Professional Colors & Badges --- */
    
    /* Price Colors */
    .text-green { color: #00a65a; font-weight: bold; } /* Selling Price */
    .text-orange { color: #f39c12; font-weight: bold; } /* Profit */
    .text-muted { color: #777; } /* Cost */

    /* Supplier Badge (New Feature) */
    .badge-supplier {
      background-color: #e8f0fe;
      color: #1967d2;
      padding: 5px 10px;
      border-radius: 4px;
      font-weight: 600;
      font-size: 12px;
      border: 1px solid #d2e3fc;
      display: inline-block;
      white-space: nowrap;
    }

    /* Stock Badges */
    .badge-service { background-color: #777; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
    .badge-zero { background-color: #dd4b39; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
    .badge-ok { background-color: #00a65a; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; }

    /* Product Code Box */
    .code-box {
      background: #f4f4f4;
      border: 1px solid #ddd;
      padding: 2px 5px;
      font-size: 12px;
      color: #555;
    }

    /* Action Icons */
    .btn-action { border: none; background: none; font-size: 14px; cursor: pointer; padding: 0 5px; }
    .icon-edit { color: #3c8dbc; }
    .icon-trash { color: #dd4b39; }

  </style>
</head>
<body>

<div class="box">
  <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Product List</h3>
  
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th width="3%">No</th>
          <th width="5%">Img</th>
          <th width="10%">Code</th>
          <th width="15%">Name</th>
          <th width="12%">Description</th>
          <th width="10%">Category</th>
          <th width="12%">Supplier</th> <th width="8%" style="text-align:right">Cost</th>
          <th width="8%" style="text-align:right">Price</th>
          <th width="8%" style="text-align:right">Profit</th>
          <th width="5%" style="text-align:center">Stock</th>
          <th width="6%" style="text-align:center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // 1. Database Connection (මෙතන ඔයාගේ DB විස්තර බලන්න)
        $conn = new mysqli("localhost", "root", "", "inventory_db");

        if ($conn->connect_error) {
           die("<tr><td colspan='12' style='color:red; text-align:center;'>Database Connection Failed! Check config.</td></tr>");
        }

        // 2. SQL Query - Supplier Join එක සහිතව
        // 's.supplier_name' ගන්නවා
        $sql = "SELECT products.*, suppliers.supplier_name 
                FROM products 
                LEFT JOIN suppliers ON products.supplier_id = suppliers.id 
                ORDER BY products.id DESC";

        $result = $conn->query($sql);

        // SQL Error Check (වැරැද්දක් තිබුනොත් කියන්න)
        if (!$result) {
            die("<tr><td colspan='12' style='color:red; text-align:center;'>SQL Error: " . $conn->error . "</td></tr>");
        }

        $count = 1;

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                
                // --- Variables සකස් කරගැනීම ---
                
                // Supplier Check
                $sup_name = !empty($row["supplier_name"]) ? $row["supplier_name"] : "Not Assigned";
                
                // Image Check
                $img_show = !empty($row["image"]) ? "uploads/".$row["image"] : "https://via.placeholder.com/40";
                
                // Profit Calculation
                $profit = $row["price"] - $row["cost"];
                
                // Stock Logic
                $stock_display = "";
                if (strtolower($row["category"]) == 'service' || strtolower($row["type"] ?? '') == 'service') {
                    $stock_display = "<span class='badge-service'>Service</span>";
                } elseif ($row["quantity"] <= 0) {
                    $stock_display = "<span class='badge-zero'>0</span>";
                } else {
                    $stock_display = "<span class='badge-ok'>".$row["quantity"]."</span>";
                }

                // --- Table Row එක Print කිරීම ---
                echo "<tr>";
                echo "<td>" . $count++ . "</td>";
                
                // Img
                echo "<td><img src='" . $img_show . "' width='35' height='35' style='border-radius:3px; border:1px solid #ddd;'></td>";
                
                // Code
                echo "<td><span class='code-box'>" . $row["code"] . "</span></td>";
                
                // Name (Bold)
                echo "<td><strong>" . $row["product_name"] . "</strong></td>";
                
                // Description
                echo "<td style='color:#666; font-size:13px;'>" . $row["description"] . "</td>";
                
                // Category
                echo "<td>" . $row["category"] . "</td>";
                
                // ===> SUPPLIER COLUMN (PRO STYLE) <===
                echo "<td><span class='badge-supplier'>" . $sup_name . "</span></td>";
                
                // Prices & Profit
                echo "<td class='text-muted' style='text-align:right'>" . number_format($row["cost"], 2) . "</td>";
                echo "<td class='text-green' style='text-align:right'>" . number_format($row["price"], 2) . "</td>";
                echo "<td class='text-orange' style='text-align:right'>" . number_format($profit, 2) . "</td>";
                
                // Stock
                echo "<td style='text-align:center'>" . $stock_display . "</td>";
                
                // Actions
                echo "<td style='text-align:center'>
                        <button class='btn-action icon-edit' title='Edit'><i class='fa fa-edit'></i></button>
                        <button class='btn-action icon-trash' title='Delete'><i class='fa fa-trash'></i></button>
                      </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='12' style='text-align:center; padding:20px; color:#999;'>No Products Found in Database.</td></tr>";
        }
        $conn->close();
        ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>