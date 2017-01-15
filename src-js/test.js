
TestCase = function (name) {
	this.reset();
	this.name = name;
	console.log('Start test ' + name);
};

TestCase.prototype.reset = function () {
	this.name = 'untitled';
	this.errors = [];
	this.started = false;
	return this;
};

TestCase.prototype.start = function () {
	console.log('Start test ' + name);
	this.started = true;
	return this;
};

TestCase.prototype.log = function (msg) {
	this.errors.push(msg);
	console.log(msg);
	return this;
};

TestCase.prototype.close = function () {
	if (this.errors.length > 0) {
		console.log('  /!\\ Test result: FAILURE');
	}
	else {
		console.log('  Test result: OK');
	}
	return this;
};

TestCase.prototype.assertEqual = function (a, b, test) {
	if (a !== b) {
		this.log('  * Test ' + test + ' failure: not equal (' + a + ' != ' + b + ')');
	}
	return this;
}

TestCase.prototype.assertNull = function (a, test) {
	if (a !== null) {
		this.log('  * Test ' + test + ' failure: not null (' + a + ')');
	}
	return this;
}

TestCase.prototype.assertNotNull = function (a, test) {
	if (a === null) {
		this.log('  * Test ' + test + ' failure: null');
	}
	return this;
}

TestCase.prototype.assertTrue = function (a, test) {
	if (a !== true) {
		this.log('  * Test ' + test + ' failure: not true (' + a + ')');
	}
	return this;
}