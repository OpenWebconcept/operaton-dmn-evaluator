<?php
// File: templates/admin-list.php
?>
<div class="wrap">
    <h1><?php _e('Operaton DMN Configurations', 'operaton-dmn'); ?></h1>
    
    <a href="<?php echo admin_url('admin.php?page=operaton-dmn-add'); ?>" class="button button-primary">
        <?php _e('Add New Configuration', 'operaton-dmn'); ?>
    </a>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Name', 'operaton-dmn'); ?></th>
                <th><?php _e('Form ID', 'operaton-dmn'); ?></th>
                <th><?php _e('Decision Key', 'operaton-dmn'); ?></th>
                <th><?php _e('Endpoint', 'operaton-dmn'); ?></th>
                <th><?php _e('Actions', 'operaton-dmn'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($configs)): ?>
                <tr>
                    <td colspan="5"><?php _e('No configurations found.', 'operaton-dmn'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($configs as $config): ?>
                    <tr>
                        <td><strong><?php echo esc_html($config->name); ?></strong></td>
                        <td><?php echo esc_html($config->form_id); ?></td>
                        <td><?php echo esc_html($config->decision_key); ?></td>
                        <td><?php echo esc_html(substr($config->dmn_endpoint, 0, 50) . '...'); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=operaton-dmn-add&edit=' . $config->id); ?>" class="button button-small">
                                <?php _e('Edit', 'operaton-dmn'); ?>
                            </a>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('delete_config'); ?>
                                <input type="hidden" name="config_id" value="<?php echo $config->id; ?>">
                                <input type="submit" name="delete_config" value="<?php _e('Delete', 'operaton-dmn'); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this configuration?', 'operaton-dmn'); ?>');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
