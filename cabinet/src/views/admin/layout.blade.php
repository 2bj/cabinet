<!DOCTYPE html>
<html lang="en" ng-app="Wardrobe">
<head>
  <title>@yield('title') | Wardrobe</title>
  <meta name="env" content="{{ App::environment() }}">
  <meta name="token" content="{{ Session::token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="{{ asset('admin/style.css') }}">
</head>
<body>
  <div id="header-region"></div>
  <div id="js-alert"></div>
  <div class="container-fluid">
    <div class="row-fluid">
      @yield('content')
      <div id="main-region" class="span12"></div>
    </div>
  </div>
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
  @yield('footer.js')
</body>
</html>