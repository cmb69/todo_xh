/**
 * JS of Todo_XH.
 *
 * Copyright (c) 2012-2013 Christoph M. Becker (see license.txt)
 */


(function($) {

    Todo = {

        sel: function(name) {
            return $.makeArray($('#todo_grid_' + name + ' .trSelected').map(function() {
                return $(this).attr('id').substr(3)
            }))
        },


        add: function(page, name) {
            var dlg = $('#todo_edit');
            dlg.dialog('option', 'buttons', [{
                text: Todo.TX.OK,
                click: function() {
                    Todo.post(page, name, null);
                    dlg.dialog('close')
                }
            }, {
                text: Todo.TX.CANCEL,
                click : function() {dlg.dialog('close')}
            }]);
            dlg.dialog({title: Todo.TX.NEW_TASK});
            var flds = ['task', 'link', 'notes', 'resp', 'state', 'date', 'vote'];
            for (var i = 0; i < flds.length; i++) {
                dlg.find('[name="todo_' + flds[i] + '"]').val('');
            }
            dlg.dialog('open')
        },


        edit: function(page, name) {
            var dlg = $('#todo_edit');
            var ids = Todo.sel(name);
            if (ids.length < 1) {
                alert(Todo.TX.SELECT_ENTRY);
                return;
            }
            var id = ids[0];
            dlg.dialog('option', 'buttons', [{
                text: Todo.TX.OK,
                click: function() {
                    Todo.post(page, name, id);
                    dlg.dialog('close')
                }
            }, {
                text: Todo.TX.CANCEL,
                click: function() {dlg.dialog('close')}
            }]);
            dlg.dialog({title: Todo.TX.TASK + ' ' + id});
            $.get('?' + page + '&todo_name=' + name + '&todo_act=get&todo_id=' + id, '', function(data) {
                rec = eval('(' + data + ')');
                for (prop in rec) {
                    dlg.find('[name="todo_' + prop + '"]').val(rec[prop]);
                }
                dlg.dialog('open')
            });
        },


        post: function(page, name, id) {
            var dlg = $('#todo_edit');
            $.post('?' + page + '&todo_name=' + name + '&todo_act=post' + (id != null ? '&todo_id=' + id : ''),
                    $('#todo_edit').serialize(),
                    function() {$('#todo_grid_' + name).flexReload()});
        },


        remove: function(page, name) {
            if (Todo.sel(name).length < 1 || !confirm(Todo.TX.CONFIRM_DELETE)) {return}
            $.post('?' + page + '&todo_name=' + name + '&todo_act=delete', {'todo_ids[]': Todo.sel(name)},
                    function() {$('#todo_grid_' + name).flexReload()});
        },


        move: function(page, name) {
            var dlg = $('#todo_move');
            dlg.dialog('option', 'buttons', [{
                text: Todo.TX.OK,
                click: function() {
                    var dname = $(this).find('select').val();
                    $.post('?' + page + '&todo_name=' + name + '&todo_act=move',
                            {'todo_ids[]': Todo.sel(name), 'todo_dest': dname},
                            function() {
                                $('#todo_grid_' + name).flexReload();
                                $('#todo_grid_' + dname).flexReload();
                            });
                    $(this).dialog('close');
                }
            }, {
                text: Todo.TX.CANCEL,
                click: function() {$(this).dialog('close')}
            }]);
            dlg.find('#todo_lists option[value="' + name + '"]').remove();
            dlg.dialog('open')
        },


        voting: function(page, name) {
            var ids = Todo.sel(name);
            if (ids.length < 1) {
                alert(Todo.TX.SELECT_ENTRY);
                return;
            }
            var id = ids[0];
            var dlg = $('#todo_voting');
            $.get('?' + page + '&todo_name=' + name + '&todo_act=voting&todo_id=' + id, '', function(data) {
                dlg.html(data);
                dlg.dialog('open')
            });
        },


        init: function(page, name) {
            var btns = !Todo.isMember ? null : [
                {name: Todo.TX.ADD, bclass: 'todo_btn_add', onpress: function() {Todo.add(page, name)}},
                {name: Todo.TX.EDIT, bclass: 'todo_btn_edit', onpress: function() {Todo.edit(page, name)}},
                {name: Todo.TX.REMOVE, bclass: 'todo_btn_remove', onpress: function() {Todo.remove(page, name)}},
                {name: Todo.TX.MOVE, bclass: 'todo_btn_move', onpress: function() {Todo.move(page, name)}},
                {name: Todo.TX.VOTING, bclass: 'todo_btn_voting', onpress: function() {Todo.voting(page, name)}}
            ];
            $('#todo_grid_' + name).flexigrid({
                url: '?' + page + '&todo_name=' + name + '&todo_act=list',
                method: 'GET',
                dataType: 'json',
                colModel: [
                    {display: Todo.TX.ID, name: 'id', width: Todo.COLS[0], sortable: true, align: 'left'},
                    {display: Todo.TX.TASK, name: 'task', width: Todo.COLS[1], sortable: true, align: 'left'},
                    {display: Todo.TX.LINK, name: 'link', width: Todo.COLS[2], sortable: true, align: 'left'},
                    {display: Todo.TX.NOTES, name: 'notes', width: Todo.COLS[3], sortable: true, align: 'left'},
                    {display: Todo.TX.RESPONSIBLE, name: 'resp', width: Todo.COLS[4], sortable: true, align: 'left'},
                    {display: Todo.TX.STATE, name: 'state', width: Todo.COLS[5], sortable: true, align: 'left'},
                    {display: Todo.TX.DATE, name: 'date', width: Todo.COLS[6], sortable: true, align: 'left'},
                    {display: Todo.TX.VOTING, name: 'votes', width: Todo.COLS[7], sortable: false, align: 'left'}
                ],
                buttons: btns,
                searchitems: [
                    {display: Todo.TX.TASK, name: 'task', isdefault: true},
                    {display: Todo.TX.NOTES, name: 'notes'}
                ],
                sortname: 'id',
                sortorder: 'asc',
                usepager: true,
                height: 'auto',
                errormsg: Todo.TX.ERRORMSG,
                pagestat: Todo.TX.PAGESTAT,
                pagetext: Todo.TX.PAGE,
                outof: Todo.TX.OUTOF,
                findtext: Todo.TX.FINDTEXT,
                procmsg: Todo.TX.PROCMSG,
                nomsg: Todo.TX.NOMSG,
                onError: function() {alert('Unknown error!')} // TODO
            })
        }
    }


    $(function() {
        var dlg = $('#todo_edit');
        dlg.dialog({autoOpen: false, modal: true, width: 536});
        dlg.find('[name="todo_date"]').datepicker({dateFormat: 'yy-mm-dd'});

        var dlg = $('#todo_move');
        var opts = dlg.find('#todo_lists').html();
        dlg.dialog({autoOpen: false, modal: true, width: 536, close: function() {
            dlg.find('#todo_lists').html(opts)
        }})

        var dlg = $('#todo_voting');
        dlg.dialog({autoOpen: false, modal: true, buttons: [{
            text: Todo.TX.OK,
            click: function() {$(this).dialog('close')}
        }]});
    })

})(jQuery)
