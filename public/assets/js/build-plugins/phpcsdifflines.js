var phpcsdifflinesPlugin = ActiveBuild.UiPlugin.extend({
    id: 'build-phpcsdifflines',
    css: 'col-lg-12 col-md-12 col-sm-12 col-xs-12',
    title: 'PHP Code Sniffer: Only changed lines',
    lastData: null,
    box: true,
    rendered: false,

    register: function() {
        var self = this;
        var query = ActiveBuild.registerQuery('phpcsdifflines-data', -1, {key: 'phpcsdifflines-data'})

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
        return $('<table class="table" id="phpcsdifflines-data">' +
            '<thead>' +
            '<tr>' +
            '   <th>'+Lang.get('file')+'</th>' +
            '   <th>'+Lang.get('line')+'</th>' +
            '   <th>'+Lang.get('message')+'</th>' +
            '</tr>' +
            '</thead><tbody></tbody></table>');
    },

    onUpdate: function(e) {
        if (!e.queryData) {
            $('#build-phpcs').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var errors = this.lastData[0].meta_value;
        var tbody = $('#phpcsdifflines-data tbody');
        tbody.empty();

        if (errors.length == 0) {
            $('#build-phpcs').hide();
            return;
        }

        for (var i in errors) {
            var file = errors[i].file;

            if (ActiveBuild.fileLinkTemplate) {
                var fileLink = ActiveBuild.fileLinkTemplate.replace('{FILE}', file);
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

        $('#build-phpcs').show();
    }
});

ActiveBuild.registerPlugin(new phpcsdifflinesPlugin());
