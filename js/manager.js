'use strict';

module.exports = function (oAppData) {
	var
		_ = require('underscore'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),

		CAbstractFileModel = require('%PathToCoreWebclientModule%/js/models/CAbstractFileModel.js')
	;

	if (App.isUserNormalOrTenant())
	{
		return {
			start: function () {
				var aExtensionsToView = oAppData['%ModuleName%'] ? oAppData['%ModuleName%']['ExtensionsToView'] : [];
				CAbstractFileModel.addViewExtensions(aExtensionsToView);
				App.subscribeEvent('FilesWebclient::ConstructView::after', function (oParams) {
					if (oParams.Name === 'CFilesView') {
						var oView = oParams.View;
						if (oView && _.isFunction(oView.registerCreateButtonsController))
						{
							oView.registerCreateButtonsController(require('modules/%ModuleName%/js/views/AddFileButtonView.js'));
						}
					}
				});
			}
		};
	}

	return null;
};
