<div style="border: 1px solid #ccc; padding: 10px; margin-top: 15px;">
    <h3>This is a partial template!</h3>
    <p>It can be included in other templates.</p>
    <p>Current time: <?php echo $this->escape($this->variables['currentTime'] ?? ''); ?></p>
</div>
