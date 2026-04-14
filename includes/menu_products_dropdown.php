<?php
// includes/menu_products_dropdown.php
// Bu parçayı üst menü (nav) içinde uygun yere include ederek kullanın.
?>
<li class="has-dropdown">
  <a href="products.php">Ürünler</a>
  <ul class="dropdown-menu">
    <li><a href="products.php">Tüm Ürünler</a></li>
    <li><a href="products.php?a=new">Yeni Ürün</a></li>
    <li><a href="taxonomies.php?t=categories">Kategoriler</a></li>
    <li><a href="taxonomies.php?t=brands">Markalar</a></li>
  </ul>
</li>
