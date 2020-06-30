Vidi for TYPO3 CMS |badge_travis| |badge_scrutinizer| |badge_coverage|
======================================================================

.. |badge_travis| image:: https://travis-ci.org/fabarea/vidi.svg?branch=master
    :target: https://travis-ci.org/fabarea/vidi

.. |badge_scrutinizer| image:: https://scrutinizer-ci.com/g/fabarea/vidi/badges/quality-score.png?b=master
   :target: https://scrutinizer-ci.com/g/fabarea/vidi

.. |badge_coverage| image:: https://scrutinizer-ci.com/g/fabarea/vidi/badges/coverage.png?b=master
   :target: https://scrutinizer-ci.com/g/fabarea/vidi

Fork to support TYPO3 10 LTS

Vidi stands for "versatile and interactive display" and is the code name of a list component
designed for listing all kind of records along with advanced filtering capabilities. By default the
extension ships two examples for FE Users / Groups but is configurable for any kind of data type.

Veni, vidi, vici!

.. image:: https://raw.github.com/fabarea/vidi/master/Documentation/List-01.png

Project info and releases
-------------------------

Home page of the project: https://github.com/fabarea/vidi

Stable version: http://typo3.org/extensions/repository/view/vidi

Development version from Git:

::

	cd typ3conf/ext
	git clone https://github.com/fabarea/vidi.git

Flash news about latest development are also announced on http://twitter.com/fudriot


Installation and requirement
============================

Install the extension as normal in the Extension Manager or download it via composer.

```
composer require fab/vidi
```

.. _TER: typo3.org/extensions/repository/

Configuration
=============

Configuration is mainly provided in the Extension Manager and is pretty much self-explanatory. Check possible options there.

User TSconfig
-------------

A pid (page id) is necessary to be defined when creating a new record for the need of TCEmain_.
This is not the case for all records as some of them can be on the root level and consequently have a pid 0.
However most require a pid value greater than 0. In a first place, a global pid can be configured in the Extension Manager
which is taken as fallback value. Besides, User TSconfig can also be set which will configure a custom pid for each data type enabling to
be fine grained. We can also define additional constraints which can be used to filter by default a Vidi module::

	# Short syntax for data type "tx_domain_model_foo":
	tx_vidi.dataType.tx_domain_model_foo.storagePid = 33

	# Extended syntax for data type "tx_domain_model_foo":
	tx_vidi {
		dataType {
			tx_domain_model_foo {
			    # Define the pid for a new record
				storagePid = 33

				# Default criterion to be used as default filter
				constraints {

				    # Records are found on pid "33"
				    # Possible operators >= > < <= = like
				    0 = pid = 33

				    # Records belongs to category 1 OR 2
				    1 = categories.uid in 1,2
				}
			}
		}
	}

.. _TCEmain: http://docs.typo3.org/TYPO3/CoreApiReference/ApiOverview/Typo3CoreEngine/UsingTcemain/Index.html


Fetching data with...
=====================

Content View Helper
-------------------

To fetch content, the ``content.find()`` View Helper can be used to retrieve data in a Fluid template. Underneath it will
interact with a Vidi Content Repository.
As example, let display a list of all Frontend Users belonging to User Group "1" and also display the User Groups they belong::

	<strong>Number of people: {v:content.count(matches: {usergroup: 3}, type: 'fe_users')}</strong>

    <f:for each="{v:content.find(matches: {usergroup: 3}, orderings: {uid: 'ASC'}, type: 'fe_users')}" as="person">
        <div>
            {person.uid}:{person.username}

            <!-- !!! Notice how you can fetch relation through their properties! -->
            <f:if condition="{person.usergroup}}">
                <ul>
                    <f:for each="{person.usergroup}" as="group">
                        <li>{group.title}</li>
                    </f:for>
                </ul>
            </f:if>
        </div>
    </f:for>
	{namespace m=Fab\Vidi\ViewHelpers}

We can do the same to retrieve **only one** record but this time with ViewHelper ``findOne``::

    <v:content.findOne type="fe_users" identifier="123">
        <div>
            <strong>{object.username}</strong>
        </div>
    </v:content.findOne>


Or given ``matches`` where multiple criteria can be cumulated. Value ``foo`` corresponds to a field name and bar to a value::

    <v:content.findOne type="fe_users" matches="{foo: bar}">
        <div>
            <strong>{object.username}</strong>
        </div>
    </v:content.findOne>


An object can be retrieved dynamically given an argument in the URL. We assume, the URL looks as follows
http://domain.tld/detail/?tx_vidifrontend_pi1[uid]=164

::

    <v:content.findOne type="fe_users" argumentName="object"">
        <div>
            <strong>{object.username}</strong>
        </div>
    </v:content.findOne>

If wanted, we can define a custom argument name with attribute ``argumentName`` and also give the object an alias with attribute ``as``.
The URL would look like: http://domain.tld/detail/?user=164

::

    <v:content.findOne type="fe_users" argumentName="object" as="user">
        <div>
            <strong>{user.username}</strong>
        </div>
    </v:content.findOne>


Vidi Content Repository (programming way)
-----------------------------------------

Each Content type (e.g. fe_users, fe_groups) has its own Content repository instance which is managed internally by the Repository Factory.
For getting the adequate instance, the repository can be fetched by this code. ::


	// Fetch the adequate repository for a known data type.
	$dataType = 'fe_users';
	$contentRepository = \Fab\Vidi\Domain\Repository\ContentRepositoryFactory::getInstance($dataType);

	// From there, you can query the repository as you are used to in Flow / Extbase.

	// Fetch all users having name "foo".
	$contentRepository->findByName('foo');

	// Fetch one user with username "foo"
	$contentRepository->findOneByUsername('foo');

	// Fetch all users belonging to User Group "1". Usergroup must be written that sort following the TCA of fe_users, column "usergroup".
	$contentRepository->findByUsergroup(1);

For complex query, a matcher object can be instantiated where to add many criteria. The matching criteria will then be interpreted by the
Content Repository. Here is an example for retrieving a set of files::

	// Initialize a Matcher object.
	/** @var \Fab\Vidi\Persistence\Matcher $matcher */
	$matcher = GeneralUtility::makeInstance('Fab\Vidi\Persistence\Matcher');

	// Add some criteria.
	$matcher->equals('storage', '1');

	// "metadata" is required and corresponds to a field path making the join between the "sys_file_metadata" and "sys_file".
	$matcher->equals('metadata.categories', '1');

	// Add criteria with "like"
	$matcher->like('metadata.title', 'foo');

	// Fetch the objects.
	$files = $contentRepository->findBy($matcher);

**Notice**: The example would work in the Frontend as well. However, not everything is in place such as localization. Having that on my todo list.

Facets
======

Facets are visible in the Visual Search and enable the search by criteria. Facets are generally mapped to a field but it is not mandatory ; it can be arbitrary values. To provide a custom Facet, the interface `\Fab\Vidi\Facet\FacetInterface` must be implemented. Best is to take inspiration of the `\Fab\Vidi\Facet\StandardFacet`.

```
# Example for table fe_users
$GLOBALS['TCA']['fe_users']['grid']['facets'][\Fab\Vidi\Facet\CompositeFacet::class] =
    [
        'name' => 'uid',
        'label' => 'LLL:EXT:my_example/Resources/Private/Language/locallang.xlf:foo',
        'configuration' => [
            'disable' => 0,
            'email' => '*',
            'pid' => 123,
        ]
    ],
```

Add tools in a Vidi module
==========================

For each Vidi module, it is possible to register some tools to do whatever maintenance, utility, processing operations for a content type.
The landing page of the Tools can be accessed by clicking the upper right icon within the BE module. The icon is only displayed if some Tools is available for the User.
To take example, there is a Tool which is shown for admin User that will check the relations used in the Grid.
To register your own Tool, add the following lines into in ``ext_tables.php``::

	if (TYPO3_MODE == 'BE') {

		// Register a Tool for a FE User content type only.
		\Fab\Vidi\Tool\ToolRegistry::getInstance()->register('*', 'Fab\Vidi\Tool\RelationAnalyserTool');


		// Register some Tools for all Vidi modules.
		\Fab\Vidi\Tool\ToolRegistry::getInstance()->register('fe_users', 'Fab\Vidi\Tool\RelationAnalyserTool');
	}

Override permissions
--------------------

Permissions whether the Tool is accessible or not is defined in the Tool class itself. We can control
through the method `isShown` what BE groups (admin, editors, ...) is allowed to access the tool.
However, the rules can be overridden programmatically via the API by adding configuration in `ext_tables`.
Assuming we want to allow the access to every BE Users for the "find duplicates" tool provided in Media_, we would do something::


	\Fab\Vidi\Tool\ToolRegistry::getInstance()
		->overridePermission('sys_file', 'Fab\Media\Tool\DuplicateFilesFinderTool', function() {
		return true;
	});

	# where:
	# * sys_file: the scope of the, can be possibly '*' for every data type.
	# * DuplicateFilesFinderTool: the name of the tool.
	# * function(): the closure to override the default permissions.

.. _Media: https://github.com/fabarea/media

TCA Grid
========

The Grid is the heart of the List component in Vidi. The TCA was extended to describe how a grid and its
columns should be rendered. Take inspiration of `this example`_ below for your own data type::

  'grid' => [
    'columns' => [
      '__checkbox' => [
        'renderer' => \Fab\Vidi\Grid\CheckBoxRenderer::class,
      ],
      'uid' => [
        'visible' => false,
        'label' => 'Id',
        'width' => '5px',
      ],
      'username' => [
        'visible' => true,
        'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:username',
      ],
      'usergroup' => [
        'visible' => true,
        'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:usergroup',
      ],
      '__buttons' => [
        'renderer' => \Fab\Vidi\Grid\ButtonGroupRenderer::class,
      ],
    ],
  ],


.. _this example: https://github.com/fabarea/vidi/blob/master/Configuration/TCA/Overrides/fe_users.php#L21


TCA "grid"
----------

::

	'grid' => [
		'excluded_fields' => 'foo, bar',
		'export' => [],
		'facets' => [],
		'columns' => []
	],

.. container:: table-row

Key
	**included_fields**

Description
	Strictly include this CSV list of fields

.. container:: table-row

Key
	**excluded_fields**


Description
	Whenever some fields should be excluded from the Grid


.. container:: table-row

Key
	**export**


Description
	Configuration for data export, CSV, XML, ...

.. container:: table-row

Key
	**facets**


Description
	Configuration for the facets

.. container:: table-row

Key
	**columns**


Description
	Configuration for the columns in the Grid


TCA "grid.columns"
------------------

Configuration of ``$GLOBALS['TCA']['tx_foo']['grid']['columns']['field_name']`` as example::

	'grid' => array(
		'columns' => array(
			'username' => array(
				'visible' => true,
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:username',
			),
		),
	),

Possible key and values that can be assigned for a field name:

.. ...............................................................
.. container:: table-row

Key
	**sortable**

Datatype
	boolean

Description
	Whether the column is sortable or not. This value is not respected if the table has a "sortby" value as:

	['ctrl']['sorby'] => 'sorting'

Default
	true


.. ...............................................................
.. container:: table-row

Key
	**visible**

Datatype
	boolean

Description
	Whether the column is visible by default or hidden. If the column is not visible by default
	it can be displayed with the column picker (upper right button in the BE module)

Default
	true

.. ...............................................................
.. container:: table-row

Key
	**renderer**

Datatype
	string

Description
	A class name implementing Grid Renderer Interface

Default
	NULL

.. ...............................................................
.. container:: table-row

Key
	**format**

Datatype
	string

Description
	A full qualified class name implementing :code:`\Fab\Vidi\Formatter\FormatterInterface`

Default
	NULL

.. ...............................................................
.. container:: table-row

Key
	**label**

Datatype
	string

Description
	An optional label overriding the default label of the field - i.e. the label from TCA['tableName']['columns']['fieldName']['label']

Default
	NULL


.. ...............................................................
.. container:: table-row

Key
	**editable**

Datatype
	string

Description
	Whether the field is editable or not.

Default
	NULL

.. ...............................................................
.. container:: table-row

Key
	**dataType**

Datatype
	string

Description
	The table name where the field belong.
	Only defines this option if the field comes from another table.
	A Grid Render will be necessary to render the content.

Default
	NULL

.. ...............................................................
.. container:: table-row

Key
	**class**

Datatype
	string

Description
	Will display the class name to every cell.

Default
	NULL

.. ...............................................................
.. container:: table-row

Key
	**wrap**

Datatype
	string

Description
	A possible wrapping of the content. Useful in case the content of the cell should be styled in a special manner.

Default
	NULL

.. ...............................................................
.. container:: table-row

Key
	**width**

Datatype
	int

Description
	A possible width of the column

Default
	NULL

.. ...............................................................
.. container:: table-row

Key
	**canBeHidden**

Datatype
	boolean

Description
	Whether the column can be hidden or not.

Default
	true


.. ...............................................................
.. container:: table-row

Key
	**localized**

Datatype
	boolean

Description
	If a field is configured to be localized in the TCA, there is the chance to force not to be localized in the Grid.

Default
	true

TCA "grid.facets"
-----------------

Configuration of ``$GLOBALS['TCA']['tx_foo']['grid']['facets']`` as example::

	'grid' => array(

		'facets' => array(
			'uid',
			'username',
			....
		),
	),


List of fields considered as facets.

TCA "grid.export"
-----------------

Configuration of ``$GLOBALS['TCA']['tx_foo']['grid']['export']`` as example::

	'grid' => array(
		'export' => array(
			'include_files' => false,
		),
	),

Possible key and values that can be assigned for key ``export``

.. container:: table-row

Key
	**include_files**

Description
	Whether to zip files along with the CSV, XML, ... file

Default
	true

.. container:: table-row

Key
	**show_wizard** (not implemented)

Description
	Display a pop up windows where it is possible to select what fields are being exported.

TCA "vidi"
----------

Special key for Vidi configuration if needed.

Configuration of ``$GLOBALS['TCA']['tx_foo']['vidi']`` as example::

	'vidi' => array(
		'mappings' => array(
			// field_name => propertyName
			'TSconfig' => 'tsConfig',
			'felogin_redirectPid' => 'feLoginRedirectPid',
			'felogin_forgotHash' => 'feLoginForgotHash',
		),
	),

Possible key and values that can be assigned for key ``vidi``

.. container:: table-row

Key
	**mappings**

Description
	Mapping rules when the field name does not follow the underscore name conventions filed_name -> propertyName
	Vidi needs a bit of help to find the equivalence.

	Example:

		"WeirdField_Name" => 'weirdFieldName'


Grid Renderer
-------------

By default the value of the column is displayed without further processing except the HTML entities conversion.
In some cases, it is wanted to customize the output for instance whenever displaying relations.
A Grid Renderer can be configured for the column as example. You can write your custom Grid Renderer, they just have to implement
Grid Renderer Interface.


Basic Grid Renderer::


	# "foo" is the name of a field and is assumed to have a complex rendering
	'foo' => array(
		'label' => 'LLL:EXT:lang/locallang_tca.xlf:tx_bar_domain_model.foo', // Label is required
		'renderer' => \Fab\Vidi\Grid\RelationRenderer::class,
	),

Grid Renderer with options::

	# "foo" is the name of a field and is assumed to have a complex rendering
	'foo' => array(
		'label' => 'LLL:EXT:lang/locallang_tca.xlf:tx_bar_domain_model.foo', // Label is required
		'renderer' => \Fab\Vidi\Grid\GenericColumn:class,
		'rendererConfiguration' => [
		    'foo' => 'bar'
		]
	),

Multiple Grid Renderers with options::

	'foo' => array(
		'label' => 'LLL:EXT:lang/locallang_tca.xlf:tx_bar_domain_model.foo', // Label is required
		'renderers' => [
		    \Fab\Vidi\Grid\RelationRenderer:class,
		    ... // more possible renderers
		],
	),


Grid Formatter
--------------

You can format the value of a column by using one of the built-in formatter of vidi or a custom formatter.

There are two built-in formatters:

* :code:`\Fab\Vidi\Formatter\Date` - formats a timestamp with d.m.Y
* :code:`\Fab\Vidi\Formatter\Datetime` - formats a timestamp with d.m.Y - H:i

If you want to provide a custom formatter, it has to implement :code:`\Fab\Vidi\Formatter\FormatterInterface`

Example, using a built-in formatter::

	'starttime' => array(
		'label' => ...
		'format' => 'Fab\Vidi\Formatter\Date',
	),

Example, using the custom FancyDate formatter from the Acme Package::

	'starttime' => array(
		'label' => ...
		'format' => 'Acme\Package\Vidi\Formatter\FancyDate',
	),

TCA Service API
===============

This API enables to fetch info related to TCA in a programmatic way. Since TCA covers a very large set of data, the service is divided in types.
There are are four parts being addressed: table, field, grid and form. The "grid" TCA is not official and is extending the TCA for the needs of Vidi.

* table: deals with the "ctrl" part of the TCA. Typical info is what is the label of the table name, what is the default sorting, etc...
* field: deals with the "columns" part of the TCA. Typical info is what configuration, label, ... has a field name.
* grid: deals with the "grid" part of the TCA.
* form: deals with the "types" (and possible "palette") part of the TCA. Get what field compose a record type.

The API is meant to be generic and can be re-use for every data type within TYPO3. Some code examples.

::

	use Fab\Vidi\Tca\Tca;

	# Return the field type
	Tca::table($tableName)->field($fieldName)->getType();

	# Return the translated label for a field
	Tca::table($tableName)->field($fieldName)->getLabel();

	# Get all field configured for a table name
	Tca::table($tableName)->getFields();

	...

Property Mapping
================

Internally, Vidi makes an automatic conversion of a field name (in the database) to a property name (of the object)
following a camel-case convention. Example ``field_name`` will be converted to ``propertyName``.

However, there could be special cases where the field name does not follow the conventions for legacy reason.
Vidi needs a bit of help to find the equivalence fieldName <-> propertyName. This can be addressed by configuration::

	# Context: $GLOBALS['fe_users']['vidi']
	# Example used for "fe_users"
	'vidi' => array(
		'mappings' => array(
			'lockToDomain' => 'lockToDomain',
			'TSconfig' => 'tsConfig',
			'felogin_redirectPid' => 'feLoginRedirectPid',
			'felogin_forgotHash' => 'feLoginForgotHash',
		),
	),

Data Handling
=============

For actions such as "update", "remove", "copy", "move", the DataHandler of the Core is configured to be used by default.
It will work fine in most cases. However, there is the chance to call your own Data Handler if there are special needs (@see FileDataHandler in EXT:media)
Another reasons, would be for speed. You will notice a performance impact when mass editing data and relying on the Core DataHandler at the same time.
While it will disconnect you from TCEmain (which handles for your hooks, cache Handling, etc... ), using your own DataHandler will make the mass processing much faster.

::

	# Context: $GLOBALS['sys_file']['vidi']
	# Example used for "sys_file"
	'vidi' => array(
		'data_handler' => array(
			// For all actions
			'*' => 'TYPO3\CMS\Media\DataHandler\FileDataHandler'

			// Or for individual action
			ProcessAction::UPDATE => 'MyVendor\MyExt\DataHandler\FooDataHandler'
		),
	),


Module Loader
=============

If you need to hook into a module and add some custom behaviour for a new button or replacing a Component,
you can configure the Module through the Vidi Module Loader. As a quick example::

	$moduleLoader = GeneralUtility::makeInstance('Fab\Vidi\Module\ModuleLoader', 'sys_file');
	$moduleLoader->addJavaScriptFiles(...)


For more insight, consider the example of ``ext_tables.php`` in `extension Media`_.

Notice also for each Vidi module, you can add any kind of utility tools in a dedicated module (c.f. Add tools in a Vidi module).

.. _extension Media: https://github.com/fabarea/media/blob/master/ext_tables.php#L61

FAQ
===

* **What about performance?**

We don't have figures. However, Vidi is quite close to the database and if the index are well configured,
Vidi modules behave quite well when dealing with large amount of data. In general, Vidi is capable to fetch just the
exact number of records required. Furthermore, Vidi is capable of internally caching data in memory such as relations once they have been fetched.

If you experiment a slow Grid, consider reducing the number of column visible among other the "relational" columns which are the most expensive to render. If a column is hidden
in the Grid, the content will not be computed for performance sake.

* **How to get started with a new custom Vidi module?**

As of 0.4.0 Vidi comes with a new experimentation in the form of a "list2". The idea is to bring all the power of Vidi to every record types without further configuration.
It can be activated in the settings of the Extension Manager. If you need further configure the Grid, take example how it is done for fe_users in file ``EXT:vidi/Configuration/Overrides/fe_users.php``.
