import './bootstrap';

//Кнопка бокового меню
(function($) {
	"use strict";
	var fullHeight = function() {

		$('.js-fullheight').css('height', $(window).height());
		$(window).resize(function(){
			$('.js-fullheight').css('height', $(window).height());
		});

	};
	fullHeight();
})(jQuery);

//Настройки ajax
$.ajaxSetup({
	headers:{'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
	contentType: false,
	processData: false,
	complete: function(xhr, stat) {
		if(xhr.status==200)
		{
			var validJSON=true;
			var json = null;
			try
			{
				json = JSON.parse(xhr.responseText);  
			}
			catch(e)
			{
				validJSON=false;
			}
			if(validJSON)
			{
				if(json.type!=undefined)
				{
					if(json.type=='alert')
					{
						alert(json.text);
					}
					else
					{
						;
					}
				}
				if(json.refresh!=undefined)
				{
					location.reload();
				}
				if(json.redirect!=undefined)
				{
					location.replace(json.redirect);
				}
			}
		}
		else if(xhr.status==419)
		{
			alert('CSRF');
			console.log('>>>>>>>>>>>>>>>>>> update CSRF - app.js <<<<<<<<<<<<<<<<<<<<<');
			$.ajax({
				url:"/get-csrf-token",
				success:function(data)
				{
					$("meta[name=csrf-token]").attr("content",data);
					$.ajaxSetup({beforeSend:function(xhr){xhr.setRequestHeader('X-CSRF-TOKEN',$('meta[name="csrf-token"]').attr('content'));}});
				}
			});
			//осталось переслать запрос заново
		}
	}
});

$(document).ready(function(e){
			//BODY CLICK
	$(document).on('click','body *',function(e){
		//console.log(e);
	});
});

/*! @preserve
 * bstreeview.js
 * Version: 1.2.1
 * Authors: Sami CHNITER <sami.chniter@gmail.com>
 * Copyright 2020
 * License: Apache License 2.0
 *
 * Project: https://github.com/chniter/bstreeview
 * Project: https://github.com/nhmvienna/bs5treeview (bootstrap 5)
 */
; (function ($, window, document, undefined){
    "use strict";
    /**
     * Default bstreeview  options.
     */
    var pluginName = "bstreeview",
        defaults = {
            expandIcon: 'fa fa-angle-down fa-fw',
            collapseIcon: 'fa fa-angle-right fa-fw',
            expandClass: 'show',
            indent: 1.25,
            parentsMarginLeft: '1.25rem',
            openNodeLinkOnNewTab: true

        };
    /**
     * bstreeview HTML templates.
     */
    var templates = {
        treeview: '<div class="bstreeview"></div>',
        treeviewItem: '<div role="treeitem" class="list-group-item" data-bs-toggle="collapse"></div>',
        treeviewGroupItem: '<div role="group" class="list-group collapse" id="itemid"></div>',
        treeviewItemStateIcon: '<i class="state-icon"></i>',
        treeviewItemIcon: '<i class="item-icon"></i>'
    };
    /**
     * BsTreeview Plugin constructor.
     * @param {*} element
     * @param {*} options
     */
    function bstreeView(element, options) {
        this.element = element;
        this.itemIdPrefix = element.id + "-item-";
        this.settings = $.extend({}, defaults, options);
        this.init();
    }
    /**
     * Avoid plugin conflict.
     */
    $.extend(bstreeView.prototype, {
        /**
         * bstreeview intialize.
         */
        init: function () {
            this.tree = [];
            this.nodes = [];
            // Retrieve bstreeview Json Data.
            if (this.settings.data) {
                if (this.settings.data.isPrototypeOf(String)) {
                    this.settings.data = $.parseJSON(this.settings.data);
                }
                this.tree = $.extend(true, [], this.settings.data);
                delete this.settings.data;
            }
            // Set main bstreeview class to element.
            $(this.element).addClass('bstreeview');

            this.initData({ nodes: this.tree });
            var _this = this;
            this.build($(this.element), this.tree, 0);
            // Update angle icon on collapse
            $(this.element).on('click', '.list-group-item', function (e) {
                $('.state-icon', this)
                    .toggleClass(_this.settings.expandIcon)
                    .toggleClass(_this.settings.collapseIcon);
                // navigate to href if present
                if (e.target.hasAttribute('href')) {
                    if (_this.settings.openNodeLinkOnNewTab) {
                        window.open(e.target.getAttribute('href'), '_blank');
                    }
                    else {
                        window.location = e.target.getAttribute('href');
                    }
                }
                else
                {
                    // Toggle the data-bs-target. Issue with Bootstrap toggle and dynamic code
                    $($(this).attr("data-bs-target")).collapse('toggle');
                }
            });
        },
        /**
         * Initialize treeview Data.
         * @param {*} node
         */
        initData: function (node) {
            if (!node.nodes) return;
            var parent = node;
            var _this = this;
			//console.log(node);
            $.each(node.nodes, function checkStates(index, node) {
				try{
					node.nodeId = _this.nodes.length;
				}catch(e){
					//console.log('node error');
					//console.log(index);
					//console.log(node);
				}
                node.parentId = parent.nodeId;
                _this.nodes.push(node);

                if (node.nodes) {
                    _this.initData(node);
                }
            });
        },
        /**
         * Build treeview.
         * @param {*} parentElement
         * @param {*} nodes
         * @param {*} depth
         */
        build: function (parentElement, nodes, depth) {
            var _this = this;
            // Calculate item padding.
            var leftPadding = _this.settings.parentsMarginLeft;

            if (depth > 0) {
                leftPadding = (_this.settings.indent + depth * _this.settings.indent).toString() + "rem;";
            }
            depth += 1;
            // Add each node and sub-nodes.
            $.each(nodes, function addNodes(id, node) {
                // Main node element.
                var treeItem = $(templates.treeviewItem)
                    .attr('data-bs-target', "#" + _this.itemIdPrefix + node.nodeId)
                    .attr('style', 'padding-left:' + leftPadding)
                    .attr('aria-level', depth);
                // Set Expand and Collapse icones.
                if (node.nodes) {
                    var treeItemStateIcon = $(templates.treeviewItemStateIcon)
                        .addClass((node.expanded)?_this.settings.expandIcon:_this.settings.collapseIcon);
                    treeItem.append(treeItemStateIcon);
                }
                // set node icon if exist.
                if (node.icon) {
                    var treeItemIcon = $(templates.treeviewItemIcon)
                        .addClass(node.icon);
                    treeItem.append(treeItemIcon);
                }
                // Set node Text.
                treeItem.append(node.text);
                // Reset node href if present
                if (node.href) {
                    treeItem.attr('href', node.href);
                }
                // Add class to node if present
                if (node.class) {
                    treeItem.addClass(node.class);
                }
                // Add custom id to node if present
                if (node.id) {
                    treeItem.attr('id', node.id);
                }
                // Attach node to parent.
                parentElement.append(treeItem);
                // Build child nodes.
                if (node.nodes) {
                    // Node group item.
                    var treeGroup = $(templates.treeviewGroupItem)
                        .attr('id', _this.itemIdPrefix + node.nodeId);
                    parentElement.append(treeGroup);
                    _this.build(treeGroup, node.nodes, depth);
                    if (node.expanded) {
                        treeGroup.addClass(_this.settings.expandClass);
                    }
                }
            });
        }
    });

    // A really lightweight plugin wrapper around the constructor,
    // preventing against multiple instantiations
    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, "plugin_" + pluginName)) {
                $.data(this, "plugin_" +
                    pluginName, new bstreeView(this, options));
            }
        });
    };
})(jQuery, window, document);

//Breakpoint
var breakpoint = null;
function showBreakPoints()
{
	//console.log('>>>>>>>>>>>>>>>>>> showBreakPoints <<<<<<<<<<<<<<<<<<<<<');
	/*
	X-Small	None	<576px
	Small	sm	≥576px
	Medium	md	≥768px
	Large	lg	≥992px
	Extra large	xl	≥1200px
	Extra extra large	xxl	≥1400px
	*/
	var divBreakPoints = $('#breakpoints');
	//console.log('check block - divBreakPoints');
	//console.log(divBreakPoints.length);
	if(divBreakPoints.length==0)
	{
		//console.log('create block - divBreakPoints');
		var box = document.createElement('div');
		box.style.position = 'fixed';
		box.style.top = '0px';
		box.style.right = '0px';
		box.style.width = '50px';
		box.style.height = '50px';
		box.style.color = 'black';
		box.style.background = 'lightblue';
		box.style.padding = '20px';
		box.id='breakpoints';
		document.body.appendChild(box);
	}
	//get width
	var wWorkspace=$(window).width();
	if(wWorkspace<576){window.breakpoint='xs';
	}else if(wWorkspace>=576 && wWorkspace<768){window.breakpoint='sm';
	}else if(wWorkspace>=768 && wWorkspace<992){window.breakpoint='md';
	}else if(wWorkspace>=992 && wWorkspace<1200){window.breakpoint='lg';
	}else if(wWorkspace>=1200 && wWorkspace<1400){window.breakpoint='xl';
	}else if(wWorkspace>=1400){window.breakpoint='xxl';}
	//console.log('breakpoint - '+window.breakpoint);
	$('#breakpoints').text(window.breakpoint);
}
$(document).ready(function(){
	showBreakPoints();
});

			//Основные функции
//Вставка html на положение каретки
function pasteHtmlAtCaret(html, selectPastedContent){
    var sel, range;
    if (window.getSelection) {
        // IE9 and non-IE
        sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount) {
            range = sel.getRangeAt(0);
            range.deleteContents();

            // Range.createContextualFragment() would be useful here but is
            // only relatively recently standardized and is not supported in
            // some browsers (IE9, for one)
            var el = document.createElement("div");
            el.innerHTML = html;
            var frag = document.createDocumentFragment(), node, lastNode;
            while ( (node = el.firstChild) ) {
                lastNode = frag.appendChild(node);
            }
            var firstNode = frag.firstChild;
            range.insertNode(frag);

            // Preserve the selection
            if (lastNode) {
                range = range.cloneRange();
                range.setStartAfter(lastNode);
                if (selectPastedContent) {
                    range.setStartBefore(firstNode);
                } else {
                    range.collapse(true);
                }
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }
    } else if ( (sel = document.selection) && sel.type != "Control") {
        // IE < 9
        var originalRange = sel.createRange();
        originalRange.collapse(true);
        sel.createRange().pasteHTML(html);
        if (selectPastedContent) {
            range = sel.createRange();
            range.setEndPoint("StartToStart", originalRange);
            range.select();
        }
    }
}

//Проверка изображения на загрузку
function IsImageOk(img){
    // During the onload event, IE correctly identifies any images that
    // weren’t downloaded as not complete. Others should too. Gecko-based
    // browsers act like NS4 in that they report this incorrectly.
    if (!img.complete) {
        return false;
    }

    // However, they do have two very useful properties: naturalWidth and
    // naturalHeight. These give the true size of the image. If it failed
    // to load, either of these should be zero.
    if (img.naturalWidth === 0) {
        return false;
    }

    // No other way of checking: assume it’s ok.
    return true;
}



        //Сокеты
let publicChannel;

$(document).ready(function () {
    console.log('Публичный канал - инициализация');

    publicChannel = Echo.channel('public-new') // Публичный канал
        .listen('.new.update', (event) => { // Слушаем событие
            console.log('Получено уведомление:', event.data);

            // Проверяем наличие message и type
            if (event.data && event.data.message && event.data.type) {
                const type = event.data.type;
                const message = event.data.message;

                // Определяем класс для модального окна и заголовка
                let alertClass = 'bg-success'; // Для заголовка
                if (type === 'danger') {
                    alertClass = 'bg-danger';
                } else if (type === 'warning') {
                    alertClass = 'bg-warning';
                }

                // Обновляем содержимое модального окна
                $('#modalBody').text(message);
                $('#modalTitle')
                    .parent() // Найти заголовок (родителя заголовка)
                    .removeClass('bg-success bg-danger bg-warning') // Удалить предыдущие классы
                    .addClass(alertClass); // Добавить новый класс

                // Показываем модальное окно
                $('#notificationModal').modal('show');
            } else {
                console.warn('Получены неполные данные уведомления:', event.data);
            }
        })
        .on('subscription_succeeded', () => {
            console.log('Подписка на публичный канал выполнена');
        })
        .on('error', (error) => {
            console.error('Ошибка подписки на публичный канал:', error);
        })
});