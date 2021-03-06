# caldavtester-tools
Tools for using CalDAVTester (https://www.calendarserver.org/CalDAVTester.html)

## Wrapper allowing to record and aggregate results of CalDAVTester ##
**caldavtests.php**
* tests can be run and recorded through this wrapper
* test-results can be aggregated by test script including a total percentage, success- and failure-count
* recorded test-results can be displayed including failure-details without re-running the tests
* each test-run has to specify a revision which gets recorded for success or failure
* allows to differenciate between still failing tests and new regressions
* test-results can be separately recorded by specifying a --branch=<branch> option
* JSON-dumps generated via testcaldav.py --observer jsondump can be imported
* lists features enabled in serverinfo.xml and available test scripts by required features
* WebGUI to display aggregate results and drill down to individual test-failures
* WebGUI allows to (re-)run test incl. fetching request/response data of failed test or all by shift click
* WebGUI allows to record notes to every test
<img width="1380" alt="screenshot" src="https://user-images.githubusercontent.com/972180/30808311-e68caf7c-a1fd-11e7-8702-b73f0d7479f6.png">

```
Usage: php caldavtests.php
--results [--branch=<branch> (default 'trunk')]
  Aggregate results by script incl. number of tests success/failure/percentage
--run[=(<script-name>|<feature>|default(default)|all)] [--branch=<branch> (default 'master')] --revision <revision>
  Run tests of given script, all scripts requiring given feature, default (enabled and not ignore-all taged) or all
--all
  Record all requests and responses, default only record them for failed tests
  Tip: use shift click in GUI to switch --all on for a single run
--result-details[=(<script-name>|<feature>|default|all(default)] [--branch=<branch> (default 'master')]
  List result details incl. test success/failure/logs
--delete=(<script>|<feature>|all) [--branch=(<branch>|all) (default 'master')]
  Delete test results of given script, all scripts requiring given feature or all
--import=<json-to-import>  --revision=<revision> [--branch=<branch> (default 'master')]
  Import a log as jsondump created with testcaldav.py --print-details-onfail --observer jsondump
--scripts[=<script-name>|<feature>|default|all (default)]
  List scripts incl. required features for given script, feature, default (enabled and not ignore-all taged) or all
--features
  List features incl. if they are enabled in serverinfo
--serverinfo
  Absolute path to serverinfo.xml to use, default './serverinfo.xml'
--testeroptions <some-options>
  Pass arbitrary options to caldavtester.py, eg. '--ssl'
--git-sources
  Absolute path to sources to use Git to automatic determine branch&revision
--gui[=[<bind-addr> (default localhost)][:port (default 8080)]]
  Run WebGUI: point your browser at given address, default http://localhost:8080/
--help|-h
  Display this help message
Options --serverinfo, --testeroptions and --gitsources need to be specified only once and get stored in .caldavtests.json.
  ```
### Installation instructions
```
git clone git@github.com:CalConnect/caldavtester.git CalDAVTester
cd CalDAVTester
git clone git@github.com:apple/ccs-pycalendar.git pycalendar
git clone git@github.com:CalConnect/caldavtester-tools
# edit serverinfo.xml: eg. url of your server, features, etc
# store config and run/record tests for first time
php caldavtester-tools/caldavtests.php --run --serverinfo=<abs. path to serverinfo.xml> --git-sources=<abs. path to your server sources>
php caldavtester-tools/caldavtests.php --gui # launch WebGUI at http://localhost:8080/
```

### ToDo
* allow to ignore single test results incl. a comment why
* import/export tests-to-ignore data
