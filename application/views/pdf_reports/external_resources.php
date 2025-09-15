<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>

<div class="external-resources-section">
    <h2><?php echo t("external_resources"); ?></h2>
    
    <?php if (!empty($resources)): ?>
        <div class="resources-list">
            <?php foreach ($resources as $resource): ?>
                <div class="resource-item">
                    <h3><?php echo htmlspecialchars($resource['title'] ?: $resource['filename'] ?: 'Resource ' . $resource['id']); ?></h3>
                    
                    <?php if (!empty($resource['description'])): ?>
                        <p><strong><?php echo t("description"); ?>:</strong> <?php echo htmlspecialchars($resource['description']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['dctype'])): ?>
                        <p><strong><?php echo t("resource_type"); ?>:</strong> <?php echo htmlspecialchars($resource['dctype']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['filename'])): ?>
                        <p><strong><?php echo t("filename"); ?>:</strong> <?php echo htmlspecialchars($resource['filename']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['file_size'])): ?>
                        <p><strong><?php echo t("file_size"); ?>:</strong> <?php echo htmlspecialchars($resource['file_size']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['format'])): ?>
                        <p><strong><?php echo t("format"); ?>:</strong> <?php echo htmlspecialchars($resource['format']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['author'])): ?>
                        <p><strong><?php echo t("author"); ?>:</strong> <?php echo htmlspecialchars($resource['author']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['date'])): ?>
                        <p><strong><?php echo t("date"); ?>:</strong> <?php echo htmlspecialchars($resource['date']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['language'])): ?>
                        <p><strong><?php echo t("language"); ?>:</strong> <?php echo htmlspecialchars($resource['language']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['rights'])): ?>
                        <p><strong><?php echo t("rights"); ?>:</strong> <?php echo htmlspecialchars($resource['rights']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($resource['notes'])): ?>
                        <p><strong><?php echo t("notes"); ?>:</strong> <?php echo htmlspecialchars($resource['notes']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if ($resource !== end($resources)): ?>
                    <hr style="margin: 20px 0;">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p><?php echo t("no_external_resources_found"); ?></p>
    <?php endif; ?>
</div>

<style>
.external-resources-section {
    margin: 20px 0;
}

.resource-item {
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}

.resource-item h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 1px solid #ccc;
    padding-bottom: 5px;
}

.resource-item p {
    margin: 5px 0;
    line-height: 1.4;
}

.resource-item strong {
    color: #555;
}
</style>
