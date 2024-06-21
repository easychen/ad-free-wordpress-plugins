(function(wp) {
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
    var Button = wp.components.Button;
    var el = wp.element.createElement;

    registerPlugin('bcwp-slug', {
        render: function() {
            return el(
                PluginPostStatusInfo,
                {},
                el(
                    Button,
                    {
                        isPrimary: true,
                        onClick: function() {
                            var title = wp.data.select('core/editor').getEditedPostAttribute('title');
                            if (!title) {
                                wp.data.dispatch('core/notices').createNotice(
                                    'error',
                                    'Title is required to generate a slug.',
                                    {
                                        isDismissible: true,
                                    }
                                );
                                return;
                            }

                            fetch(bcwpSlug.restUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': bcwpSlug.nonce,
                                },
                                body: JSON.stringify({ title: title }),
                            })
                                .then(function(res) {
                                    if (!res.ok) {
                                        return res.json().then(function(data) {
                                            throw new Error(data.message);
                                        });
                                    }
                                    return res.json();
                                })
                                .then(function(data) {
                                    wp.data.dispatch('core/notices').createNotice(
                                        'success',
                                        'Slug generated: ' + data.slug,
                                        {
                                            isDismissible: true,
                                        }
                                    );
                                })
                                .catch(function(error) {
                                    wp.data.dispatch('core/notices').createNotice(
                                        'error',
                                        error.message,
                                        {
                                            isDismissible: true,
                                        }
                                    );
                                });
                        }
                    },
                    'Generate Slug'
                )
            );
        }
    });
})(window.wp);