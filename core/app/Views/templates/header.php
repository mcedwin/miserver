<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?php echo $meta->title ?></title>
    <meta name="description" content="<?php echo $meta->description ?>" />
    <link rel="stylesheet" href="<?php echo base_url('/core/assets/lib/bootstrap5/css/bootstrap.min.css') ?>" />
    <link rel="stylesheet" href="<?php echo base_url('/core/assets/lib/fontawesome6/css/all.min.css') ?>" />
    <script src="<?php echo base_url('core/assets/lib/jquery-3.6.0.min.js') ?>"></script>

    <link href="<?php echo base_url('core/assets/css/style.css') ?>" rel="stylesheet" media="all">
    <?php echo $css ?? '' ?>

    <!--  Essential META Tags -->
    <meta property="og:title" content="<?php echo $meta->title ?>">
    <meta property="og:type" content="article" />
    <meta property="og:image" content="<?php echo $meta->image ?>">
    <meta property="og:url" content="<?php echo $meta->url ?>">

    <!--  Non-Essential, But Recommended -->
    <meta property="og:description" content="<?php echo $meta->description ?>">
    <meta property="og:site_name" content="<?php echo $meta->site_name ?>">

</head>

<body>
    <div class="wrapper">