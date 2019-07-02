/**
 * required Base.js
 * 
 * refresh : true -> remove previously set button
 * 
 * TODO: refactor this -> полностью убрать зависимость от HTML
 * TODO: научится вызывать инициализировать кнопку с сервера
 * 
 *	на сервере есть некий html шаблон -> с переменной {{button1}}
 *	
 *	мы заменяем этот button1 на кнопку
 *	
 * 
 */

var Buttons = {//factory method
    list: {},
    create: function(p) {
	return this.place(p);
    },
    place: function(p) {
	if (p.id && this.list[p.id]) {//object already present
	    var but = this.list[p.id];
	    if (p.refresh) {
		but.remove();
		this.list[p.id] = null;
	    } else {
		but.show();
		return but;
	    }
	}
	var but = new Button(p);
	this.list[but.id] = but;
	return but;
    }
};

/*
 * new Button({
 *      cls : array or string
 *      css
 *      id
 *      title
 *      action : function(){
 *          
 *      }
 *      status: enabled/disabled
 * })
 * 
 */

var Button = function(p) {

    this.hide = function() {
	this.$.hide();
    };

    this.show = function() {
	this.$.show();
    };

    this.remove = function() {
	this.$.remove();
    };

    this.place = function(p) {
	var id = p.id || Math.round(Math.random() * 10000);

	this.host = p.host || document.body;

	this.status = p.status
		? p.status
		: 'enabled';

	this.id = id;

	var that = this;

	if ($('.button_' + id).length) {
	    if (p.id) {
		console.error('Object with ' + id + ' already present');
		return false;
	    }
	    that.place(p); //this if generated id already present
	    return false;
	}

	var obj = Base.appendOnce({
	    host: Base.appendOnce({
		html: '<div class="raw_controls invisible pa"></div>',
		selector: '.raw_controls'
	    }),
	    html: '<div class="raw_button_host pr"><table class="raw_button button"><tr><td class="button_text ac vm"></td></tr></table></div>',
	    selector: '.raw_button_host'
	}).clone();

	$('.raw_button', obj).removeClass('raw_button').addClass('button_' + id);
	obj.removeClass('raw_button_host').addClass('button_host_' + id).addClass('button_host').appendTo(this.host);

	obj.show();

	this.$ = $('.button_' + id);

	$('.button_text', this.$).html(p.title);

	//add css 

	if (p.outer) {
	    if (p.outer.css) {
		$('.button_host_' + id).css(p.outer.css);
	    }
	}

	if (p.css) {
	    this.$.css(p.css);
	}

	if (p.cls) {
	    //console.log(p);
	    if (p.cls.join) {
		this.$.addClass(p.cls.join(' '));
	    } else {
		this.$.addClass(p.cls);
	    }
	}

	if (p.onDisable) {
	    this.onDisable = p.onDisable;
	} else {
	    this.onDisable = false;
	}

	//console.log(this.onDisable);

	var that = this;

	this.$.unbind('mousedown').mousedown(function() {
	    if (that.status === 'enabled') {
		Base.press($(this));
		if (p.action) {
		    p.action(that.id);
		}
	    } else {
		if (that.onDisable) {
		    that.onDisable(that.id);
		} else {
		    console.log(that.status);
		}
	    }
	});
	
	if (this.status !== 'enabled'){
	    this.disable();
	}

    };


    this.disable = function() {
	this.$.css({
	    opacity: 0.5,
	    cursor: 'default'
	});
	this.status = 'disabled'
    };

    this.enable = function() {
	this.$.css({
	    opacity: 1,
	    cursor: 'pointer'
	});
	this.status = 'enabled';
    }

    this.place(p);

};