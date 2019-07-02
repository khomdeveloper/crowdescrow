var Client = {
	timer: false,
	self_time: {
		start_time: 0,
		count: 0, //to calc average time difference
		total: 0, //to calc average tiem difference
	},
	observer_time: {
		start_time: 0
	},
	time_offset: false, //time difference between client and server
	ping: false, //main ping
	server_time: false,
	observePing: function() {
		this.self_time.start_time = Date.now();
		B.get({
			r: 'user/main',
			Unit: 'ping'
		});
	},
	getTimeOffset: function(input) {
		var that = this;
		var client_time = Date.now();
		var ping = client_time - this.self_time.start_time;
		this.self_time.count += 1;
		this.self_time.total += (input.server_time + ping / 2000 - client_time / 1000);
		this.time_offset = this.self_time.total / this.self_time.count;

		//console.log(ping);

		//console.log('time_offset (server - client)',this.time_offset);

		setTimeout(function() {
			that.observePing();
		}, input.delay
				? input.delay
				: that.frequency);
	},
	getPing: function(input) {
		var client_time = Date.now();
		this.ping = client_time - this.observer_time.start_time;
		this.server_time = input.server_time + this.ping / 2000;
		//console.log(this.server_time);
	},
	frequency: 250,
	command: 'stop', //by default stop the rocket
	ignoreResponse: false,
	removeIgnoreFlag: function(input) { //remove ignoreResponseflag
		if (input.mark == this.ignoreResponse) {
			this.ignoreResponse = false;
		}
	},
	observe: function(time) {
		var that = this;

		that.observer_time.start_time = Date.now();

		if (time) {
			that.frequency = time;
		}

		var mark = Math.round(Math.random() * 1000000);

		Base.get({
			r: 'user/main',
			Field: 'status',
			mark: mark,
			command: that.command,
			no: function() {
				that.reStart(); //restart heartbit on error
			}
		});

		that.command = false;

		return mark;
	},
	reStart: function(input) {
		var that = this;
		that.removeIgnoreFlag(input);
		that.stop(); //clean timer to be sure that it is not called in two chains
		that.timer = setTimeout(function() {
			that.observe();
		}, that.frequency);
	},
	stop: function() {
		var that = this;
		if (that.timer) {
			clearTimeout(that.timer);
			that.timer = false;
		}
	}
};