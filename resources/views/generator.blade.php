<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CRUD Generator</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @viteReactRefresh
    @vite('resources/js/mic-sole.tsx')
</head>
<body>
    <div id="crud-generator-root"></div>
</body>
</html>
