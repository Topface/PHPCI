var phpcsdifflinesPlugin = PHPCI.UiPlugin.extend({
    id: 'build-phpcsdifflines',
    css: 'col-lg-12 col-md-12 col-sm-12 col-xs-12',
    title: 'PHP Code Sniffer: Only changed lines',
    lastData: null,
    box: true,
    rendered: false,

    register: function() {
        var self = this;
        var query = PHPCI.registerQuery('phpcsdifflines-data', -1, {key: 'phpcsdifflines-data'})

        $(window).on('phpcsdifflines-data', function(data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function() {
            if (!self.rendered) {
                query();
            }
        });
    },

    render: function() {
        return $('<table class="table table-striped" id="phpcsdifflines-data">' +
            '<thead>' +
            '<tr>' +
            '   <th>File</th>' +
            '   <th>Line</th>' +
            '   <th>Message</th>' +
            '</tr>' +
            '</thead><tbody></tbody></table>');
    },

    onUpdate: function(e) {
        if (!e.queryData) {
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var errors = this.lastData[0].meta_value;
        var tbody = $('#phpcsdifflines-data tbody');
        tbody.empty();

        for (var i in errors) {
            var file = errors[i].file;

            if (PHPCI.fileLinkTemplate) {
                var fileLink = PHPCI.fileLinkTemplate.replace('{FILE}', file);
                fileLink = fileLink.replace('{LINE}', errors[i].line);

                file = '<a target="_blank" href="'+fileLink+'">' + file + '</a>';
            }

            var row = $('<tr>' +
                '<td>'+file+'</td>' +
                '<td>'+errors[i].line+'</td>' +
                '<td>'+errors[i].message+'</td></tr>');

            if (errors[i].type == 'ERROR') {
                row.addClass('danger');
            }

            tbody.append(row);
        }
    }
});

PHPCI.registerPlugin(new phpcsdifflinesPlugin());
