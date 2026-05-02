<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Freeplane Mindmap Emulator</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div id="app">
    <h1>Freeplane Mindmap Emulator</h1>
    <div id="toolbar">
        <button onclick="addRootNode()">Add Root Node</button>
        <button onclick="exportMindmap()">Export Mindmap</button>
        <input type="file" id="importFile" style="display:none" onchange="importMindmap(event)">
        <button onclick="document.getElementById('importFile').click()">Import Mindmap</button>
    </div>
    <div id="mindmap"></div>
</div>
<script src="mindmap.js"></script>
</body>
</html>