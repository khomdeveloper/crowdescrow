//factory method
var Videos = {
};


/**
 * @param {type} p
 * 
 *		{
 *		    host : host where we place container
 *		    path : 'img/thank/',
 *		    extention : '.png' by default
 *		    delay: by default (1/25 sec)  = 40 ms
 *		    restart : false,
 *		    max_frame : fix loading frames because of onerror can not be triggered
 *		    onComplete : function(){
 *			//operation when complete the loop
 *		    }
 *		    onLoad : function(){
 *			//operation when complete loading
 *		    }
 *		}
 * 
 * @returns {Video}
 */

var Video = function(p) {

    this.loaded_frames = 0;
    this.total_frames = 0;
    this.current_frame = 0;

    //create frame html
    this.createFrame = function(frame) {
	var res = frame < 100
		? (frame < 10
			? '00' + frame
			: '0' + frame)
		: frame;
	return '<img class="frame frame_' + frame + '" style="display:none; position:absolute; left:0px; top:0px; width:295px; height:166px;" width="295" height="166" src="' + this.path + res + (this.extension || '.png') + '" alt="' + frame + '"/>';
    };

    //try to upload sequence
    /**
     * 
     * @param {type} input
     *	    input.host
     *	    input.total_frames
     * 
     * @returns {undefined}
     */
    this.load = function(input) {

	var that = this;

	that.loaded_frames = 0;
	that.total_frames = input.total_frames;

	//add frames html to container
	var h = [];
	for (var i = input.total_frames - 1; i >= 0; i--) {
	    h.push(that.createFrame(i));
	}
	input.host.html(h.join(''));

	$('.frame').unbind('error').unbind('load').load(function() {
	    that.loaded_frames += 1;
	    //console.log(that.loaded_frames, that.total_frames);
	    if (that.loaded_frames === that.total_frames) { //loading completed
		if (input.onComplete) {
		    input.onComplete();
		}
	    }
	}).error(function() {
	    console.error('error during loading frames');
	});

    };

    this.start = function(p) {

	var that = this;

	p.host.hide();

	if (!p.id || !p.path) {
	    console.error('need to set up "id" for video container and "path" where sequence is stored');
	}

	this.path = p.path;

	var container = Base.appendOnce({
	    host: p.host,
	    selector: '.video_' + p.id,
	    html: '<div class="video_' + p.id + '"></div>'
	});

	this.frames_loaded = 0;

	this.load({
	    total_frames: p.total_frames,
	    host: container,
	    onComplete: function() {
		that.current_frame = 0;
		that.delay = p.delay || 40;
		p.host.show();
		if (p.onLoad) {
		    p.onLoad();
		}
		that.play(p);
	    }
	});

    };

    this.play = function(p) {
	var that = this
	$('.frame_' + that.current_frame).hide();
	that.current_frame += 1;
	$('.frame_' + that.current_frame).show();
	//console.log(that.current_frame, that.total_frames);
	if (that.current_frame < that.total_frames){
	    setTimeout(function() {
		that.play(p);
	    }, that.delay);
	} else {
	    if (p.onComplete) {
		p.onComplete();
	    }
	}
    };

    this.start(p);
};