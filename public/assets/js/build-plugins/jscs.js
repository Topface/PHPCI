var jscsPlugin = ActiveBuild.UiPlugin.extend({
    id: 'build-jscs',
    css: 'col-lg-12 col-md-12 col-sm-12 col-xs-12',
    title: 'JSCS',
    lastData: null,
    box: true,
    rendered: false,

    register: function() {
        var self = this;
        var query = ActiveBuild.registerQuery('jscs-data', -1, {key: 'jscs-data'})

        $(window).on('jscs-data', function(data) {
            self.onUpdate(data);
        });

        $(window).on('build-updated', function() {
            if (!self.rendered) {
                query();
            }
        });
    },

    render: function() {
        return $('<div class="table-responsive"><table class="table" id="jscs-data">' +
            '<thead>' +
            '<tr>' +
            '   <th>name</th>' +
            '   <th>message</th>' +
            '   <th>line</th>' +
            '</tr>' +
            '</thead><tbody></tbody></table></div>');
    },

    onUpdate: function(e) {
        if (!e.queryData) {
            $('#build-jscs').hide();
            return;
        }

        this.rendered = true;
        this.lastData = e.queryData;

        var errors = this.lastData[0].meta_value;
        var tbody = $('#jscs-data tbody');
        tbody.empty();

        if (errors.length == 0) {
            $('#build-jscs').hide();
            return;
        }

        console.log(errors);
        for (var i in errors) {
            var file = errors[i].name;

            if (ActiveBuild.fileLinkTemplate) {
                var fileLink = ActiveBuild.fileLinkTemplate.replace('{FILE}', file);
                fileLink = fileLink.replace('{LINE}', errors[i].line);

                file = '<a target="_blank" href="'+fileLink+'">' + file + '</a>';
            }

            //console.log(errors[i].name);
            //console.log(errors[i]);
            var row = $('<tr>' + '<td>'+file+'</td><td>'+errors[i].msg+'</td><td>'+errors[i].line+'</td></tr>');

                row.addClass('danger');

            tbody.append(row);
        }

        $('#build-jscs').show();
    }
});

ActiveBuild.registerPlugin(new jscsPlugin());
