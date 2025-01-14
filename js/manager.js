'use strict';

module.exports = function (oAppData) {
	var
		_ = require('underscore'),
		$ = require('jquery'),
		ko = require('knockout'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
		Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),

		App = require('%PathToCoreWebclientModule%/js/App.js'),

		CAbstractFileModel = require('%PathToCoreWebclientModule%/js/models/CAbstractFileModel.js'),
		
		CAddFileButtonView = require('modules/%ModuleName%/js/views/CAddFileButtonView.js'),
		FilesActions = require('modules/%ModuleName%/js/utils/FilesActions.js'),

		FilesSettings = require('modules/FilesWebclient/js/Settings.js')
	;

	const addFileButtonView = ko.observable(null)
	const executeCommand = (view, sCommandName) => {
		const oView = view()
		if (oView) {
			if (oView[sCommandName]) {
				const command = oView[sCommandName]
				if (command.canExecute()) { command() }
			}
		} else if (ko.isObservable(addFileButtonView)) {
			const subscription = addFileButtonView.subscribe(function (oView) {
				if (oView && oView[sCommandName]) {
					const command = oView[sCommandName]
					if (command.canExecute()) { command() }
				}
				subscription.dispose()
			})
		}
	}
	if (App.isUserNormalOrTenant())
	{
		return {
			start: function (ModulesManager) {
				var aExtensionsToView = oAppData['%ModuleName%'] ? oAppData['%ModuleName%']['ExtensionsToView'] : [];
				aExtensionsToView = aExtensionsToView.map((item) => { return Types.pString(item).toLowerCase() });
				CAbstractFileModel.addViewExtensions(aExtensionsToView);

				App.subscribeEvent('FilesWebclient::ConstructView::after', function (oParams) {
					if (oParams.Name === 'CFilesView') {
						var oView = oParams.View;
						if (oView && _.isFunction(oView.registerCreateButtonsController)) {
							addFileButtonView(new CAddFileButtonView(oView.storageType, oView.currentPath))
							oView.registerCreateButtonsController(addFileButtonView());
						}
					}
				});

				App.subscribeEvent('FilesWebclient::ParseFile::after', function (aParams) {
					var
						oFile = aParams[0],
						oRawData = aParams[1],
						sFileExtension = Types.pString(oFile.extension()).toLowerCase()
					;

					if (oFile.hasAction('view') && oFile.oActionsData['view'] && -1 !== $.inArray(sFileExtension, aExtensionsToView))
					{
						delete oFile.oActionsData['view'].HandlerName;
						oFile.oActionsData['view'].Handler = FilesActions.view.bind(oFile);
					}
					if (oFile.hasAction('convert')) {
						oFile.removeAction('convert');
						if (oFile.oActionsData['convert']) {
							oFile.actions.unshift('convert');
							oFile.oActionsData['convert'].Text = TextUtils.i18n('%MODULENAME%/ACTION_EDIT_FILE');
							oFile.oActionsData['convert'].Handler = FilesActions.convert.bind(oFile);
						}
						if (oFile.hasAction('view'))
						{
							oFile.removeAction('view');
							oFile.actions.push('view');
						}
					}
					if (oFile.hasAction('edit'))
					{
						oFile.removeAction('edit');
						if (oFile.oActionsData['edit'])
						{
							oFile.actions.unshift('edit');
							oFile.oActionsData['edit'].Text = TextUtils.i18n('%MODULENAME%/ACTION_EDIT_FILE');
							oFile.oActionsData['edit'].Handler = FilesActions.edit.bind(oFile);
						}
						if (oFile.hasAction('view'))
						{
							oFile.removeAction('view');
							oFile.actions.push('view');
						}
					}
				});

				App.broadcastEvent('RegisterNewItemElement', {
					'title': TextUtils.i18n('%MODULENAME%/ACTION_CREATE_DOCUMENT'),
					'handler': () => {
						// check if we are on Files screen or not
						if (!window.location.hash.startsWith('#' + FilesSettings.HashModuleName)) {
							window.location.hash = FilesSettings.HashModuleName
						}

						executeCommand(addFileButtonView, 'createDocumentCommand')
					},
					'className': 'item_document',
					'order': 5,
					'column': 2
				})

				App.broadcastEvent('RegisterNewItemElement', {
					'title': TextUtils.i18n('%MODULENAME%/ACTION_CREATE_SPREADSHEET'),
					'handler': () => {
						// check if we are on Files screen or not
						if (!window.location.hash.startsWith('#' + FilesSettings.HashModuleName)) {
							window.location.hash = FilesSettings.HashModuleName
						}

						executeCommand(addFileButtonView, 'createSpreadSheetCommand')
					},
					'className': 'item_spreadsheet',
					'order': 5,
					'column': 2
				})
				
				App.broadcastEvent('RegisterNewItemElement', {
					'title': TextUtils.i18n('%MODULENAME%/ACTION_CREATE_PRESENTATION'),
					'handler': () => {
						// check if we are on Files screen or not
						if (!window.location.hash.startsWith('#' + FilesSettings.HashModuleName)) {
							window.location.hash = FilesSettings.HashModuleName
						}
						
						executeCommand(addFileButtonView, 'createPresentationCommand')
					},
					'className': 'item_presentation',
					'order': 5,
					'column': 2
				})
			}
		};
	}

	return null;
};
