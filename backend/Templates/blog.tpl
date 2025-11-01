<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title></title>
</head>
<body>
  <div id="output"></div>

  <script type="module">
    // Import von markdown-it als ES‑Modul
    import markdownIt from 'https://cdn.jsdelivr.net/npm/markdown-it@14.1.0/+esm';

    const md = markdownIt();               // Instanz erzeugen
    const output = document.getElementById('output');

    // Markdown-Inhalt vom Server als JavaScript-escaped Variable verwenden
    const markdownString = `{{ $blogContentJs }}`;

    // Rendering des dynamischen Markdown-Inhalts
    output.innerHTML = md.render(markdownString);
  </script>
</body>
</html>
