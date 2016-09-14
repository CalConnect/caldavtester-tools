# caldavtester-tools
Tools for using CalDAVTester (https://www.calendarserver.org/CalDAVTester.html)

## caldavtest.php
Wrapper allowing to record and aggregate results of CalDAVTester:
* tests can be run and recorded through this wrapper
* test-results can be aggregated by test script including a total percentage, success- and failure-count
* recorded test-results can be displayed including failure-details without re-running the tests
* each test-run has to specify a revision which gets recorded for success or failure
* allows to differenciate between still failing tests and new regressions
* test-results can be separately recorded by specifying a --branch=<branch> option
* JSON-dumps generated via testcaldav.py --observer jsondump can be imported
* lists features enabled in serverinfo.xml and available test scripts by required features
* future plans include a WebGUI to display aggregate results and drill down to individual test-failures
```
Usage: php caldavtests.php
--results [--branch=<branch> (default 'trunk')]
  Aggregate results by script incl. number of tests success/failure/percentage
--run[=(<script-name>|<feature>|default(default)|all)] [--branch=<branch> (default 'master')] --revision <revision>
  Run tests of given script, all scripts requiring given feature, default (enabled and not ignore-all taged) or all
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
  Path to serverinfo.xml to use, default './serverinfo.xml'
--git-sources
  Path to sources to use Git to automatic determine branch&revision
--gui[=[<bind-addr> (default localhost)][:port (default 8080)]]
  Run WebGUI: point your browser at given address, default http://localhost:8080/
--help|-h
  Display this help message
  ```
