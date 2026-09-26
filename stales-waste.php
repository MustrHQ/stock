<?php
/** Renamed in 1.5 — kept so old bookmarks and home-screen shortcuts still land somewhere. */
header('Location: product-waste.php'.(empty($_SERVER['QUERY_STRING']) ? '' : '?'.$_SERVER['QUERY_STRING']), true, 301);
