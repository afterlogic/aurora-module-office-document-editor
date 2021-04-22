'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),

	CAbstractPopup = require('%PathToCoreWebclientModule%/js/popups/CAbstractPopup.js')
;

/**
 * @constructor
 */
function CConvertPopup()
{
	CAbstractPopup.call(this);

	this.convertInProgress = ko.observable(false);

	this.fConvertCallback = null;
	this.fViewCallback = null;
}

_.extendOwn(CConvertPopup.prototype, CAbstractPopup.prototype);

CConvertPopup.prototype.PopupTemplate = '%ModuleName%_ConvertPopup';

/**
 * @param {Function} fConvertCallback
 * @param {Function} fViewCallback
 */
CConvertPopup.prototype.onOpen = function (fConvertCallback, fViewCallback)
{
	this.convertInProgress(false);

	this.fConvertCallback = fConvertCallback;
	this.fViewCallback = fViewCallback;
};

CConvertPopup.prototype.convert = function ()
{
	if (_.isFunction(this.fConvertCallback))
	{
		this.convertInProgress(true);
		this.fConvertCallback(this.closePopup.bind(this), this.convertInProgress);
	}
};

CConvertPopup.prototype.view = function ()
{
	if (_.isFunction(this.fViewCallback))
	{
		this.fViewCallback();
	}

	this.closePopup();
};

CConvertPopup.prototype.cancelPopup = function ()
{
	this.closePopup();
};

module.exports = new CConvertPopup();
