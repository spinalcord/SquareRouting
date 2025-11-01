<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title></title>
  <style>
    #output {
      opacity: 0;
      animation: fadeIn 0.3s ease-in-out forwards;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .loading {
      opacity: 1;
      animation: none;
    }
  </style>
</head>
<body>
  <div id="output" class="loading">
    <p>Loading...</p>
  </div>

  <script type="module">
    // Import von markdown-it als ES‑Modul
    import markdownIt from 'https://cdn.jsdelivr.net/npm/markdown-it@14.1.0/+esm';

    const md = markdownIt();               // Instanz erzeugen
    const output = document.getElementById('output');
    const blogPath = `{{ $blogPath }}`;

    // Markdown-Inhalt via AJAX fetchen
    async function loadBlogContent() {
      try {
        const response = await fetch(`/blog/${blogPath}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          }
        });

        if (!response.ok) {
          throw new Error('Blog post not found');
        }

        const data = await response.json();

        if (data.success) {
          // Rendering des dynamischen Markdown-Inhalts
          output.innerHTML = md.render(data.content);
        } else {
          output.innerHTML = `<p>Error: ${data.error}</p>`;
        }
      } catch (error) {
        output.innerHTML = `<p>Error loading blog content: ${error.message}</p>`;
      }
    }

    // Content laden wenn Seite ready ist
    loadBlogContent();
  </script>
</body>
</html>
