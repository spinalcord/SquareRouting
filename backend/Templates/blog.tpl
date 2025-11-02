<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title></title>
  <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.8/dist/htmx.min.js"></script>
  <script src=" https://cdn.jsdelivr.net/npm/markdown-it@14.1.0/dist/markdown-it.min.js "></script>
</head>
<body>

<div
  id="output"
  class="loading"
  hx-post="/blog/{{ $blogPath }}"
  hx-trigger="load"
  hx-swap="innerHTML"
  hx-on::before-swap="event.detail.serverResponse = markdownit().render(event.detail.serverResponse)">
</div>
  
</body>
</html>
