<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Markdown‑It Demo</title>
</head>
<body>
  <!-- Die Textarea wurde entfernt, da wir jetzt einen String verwenden -->
  <div id="output"></div>

  <script type="module">
    // Import von markdown-it als ES‑Modul
    import markdownIt from 'https://cdn.jsdelivr.net/npm/markdown-it@14.1.0/+esm';

    const md = markdownIt();               // Instanz erzeugen
    const output = document.getElementById('output');
    
    // Markdown-Inhalt als String definieren
    const markdownString = `# Hello
This is **Markdown**.`;

    // Rendering des Strings
    output.innerHTML = md.render(markdownString);
  </script>
</body>
</html>
