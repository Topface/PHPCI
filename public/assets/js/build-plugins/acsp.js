var acspPlugin = ActiveBuild.UiPlugin.extend({
    id: 'build-acsp',
    css: 'col-lg-12 col-md-12 col-sm-12 col-xs-12',
    title: 'AclNotLinkedPermission ( https://tasks.verumnets.ru/issues/34160 )',
    lastData: null,
    box: true,
    rendered: false,

    register: function() {
        var self = this;
        var query = ActiveBuild.registerQuery('acsp-data', -1, {key: 'acsp-data'})

        $(window).on('acsp-data', function(data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function() {
            if (!self.rendered) {
                query();
            }
        });
    },

    render: function() {
        return $('<div class="table-responsive"><table class="table" id="acsp-data">' +
            '<thead>' +
            '<tr>' +
            '   <th>name</th>' +
            '</tr>' +
            '</thead><tbody></tbody></table></div>');
    },

    onUpdate: function(e) {
        if (!e.queryData) {
            $('#build-acsp').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var errors = this.lastData[0].meta_value;
        var tbody = $('#acsp-data tbody');
        tbody.empty();

        if (errors.length == 0) {
            $('#build-acsp').hide();
            return;
        }

        for (var i in errors) {
            var file = errors[i].file;

            //if (ActiveBuild.fileLinkTemplate) {
            //    var fileLink = ActiveBuild.fileLinkTemplate.replace('{FILE}', file);
            //    fileLink = fileLink.replace('{LINE}', errors[i].line);
            //
            //    file = '<a target="_blank" href="'+fileLink+'">' + file + '</a>';
            //}

            //console.log(errors[i].name);
            var row = $('<tr>' + '<td>'+errors[i].name+'</td></tr>');

                row.addClass('danger');

            tbody.append(row);
        }

        $('#build-acsp').show();
    }
});

ActiveBuild.registerPlugin(new acspPlugin());
