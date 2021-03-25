'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),

	CAbstractPopup = require('%PathToCoreWebclientModule%/js/popups/CAbstractPopup.js')
;

/**
 * @constructor
 */
function CCreateDocumentPopup()
{
	CAbstractPopup.call(this);
	
	this.fCallback = null;
	this.filename = ko.observable('');
	this.filename.focus = ko.observable(false);
	this.filename.error = ko.observable('');

	this.filename.subscribe(function () {
		this.filename.error('');
	}, this);
}

_.extendOwn(CCreateDocumentPopup.prototype, CAbstractPopup.prototype);

CCreateDocumentPopup.prototype.PopupTemplate = '%ModuleName%_CreateDocumentPopup';

/**
 * @param {Function} fCallback
 */
CCreateDocumentPopup.prototype.onOpen = function (sBlankName, fCallback)
{
	this.filename(sBlankName);
	this.filename.focus(true);
	this.filename.error('');

	if ($.isFunction(fCallback))
	{
		this.fCallback = fCallback;
	}
};

CCreateDocumentPopup.prototype.create = function ()
{
	this.filename.error('');

	if (this.fCallback)
	{
		var sError = this.fCallback(this.filename());
		if (sError)
		{
			this.filename.error('' + sError);
		}
		else
		{
			this.closePopup();
		}
	}
	else
	{
		this.closePopup();
	}
};

module.exports = new CCreateDocumentPopup();
