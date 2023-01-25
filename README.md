# Lagoon Advanced Task Toolbox

This application provides a generic toolbox that is meant to abstract generic in-namespace administrative functions,
typically invoked from inside a Lagoon custom (image) task.

Scripts are written using an ansible-like yaml format. In general the structure of the scripts are as follows

```
prerequisites:
    - <script steps appear here>
steps:
    - <script steps appear here>
rollback:
    - <steps to rollback appear here>
```

There are three top level sequences, `prerequisites`, `steps` and `rollback`. The entries in `steps` specify the actual
steps that will be run when the application is invoked, while the (optional) `rollback` sequence defines steps that are
run if any of the steps in `steps` sequence fail. Any steps that can appear in the `steps` sequence can appear
in `rollback`. The `prerequisites` array is optional and will play any steps that can appear in `steps` except that if
any of these fail, the task will exit and the rollback steps *will not be run*.
`prerequisites` is a very good place to run any *assertions* (below) to ensure that the task can and should be run on
the target environment.

The kinds of steps supported are described below.

---

## Using the Advanced Task Toolbox

The Advanced Task Toolbox is (currently) a [Robo](https://robo.li/) application, and requires PHP 8.1. The Dockerfile in
this repository is based on the `uselagoon/php-8.1-cli` image.

By _default_ if you don't explicitly invoke the application with a particular script by using the `--migrateYaml`
option, the task toolbox will look for a task argument (in the task's JSON_PAYLOAD) called `migrateYaml`. If neither are
provided, the task will not run.

There are two potential ways of using this application.

First:

1. Create a new dockerfile with `FROM amazeeio/advanced-task-toolbox:latest`
2. `COPY` any scripts you need into `/app/scripts`
3. Publish your new image and use it in a custom advanced task.

Second:

1. Fork the repo
2. Add your scripts to ./scripts/
3. Publish an image to use in a custom task

**Note** you will need to create a custom task of `type: IMAGE`, which is currently only available to platform
owners/admins. Advanced tasks are potentially very destructive and should be used _very carefully_.

***

## Assertions

Assertions are simple boolean checks that will throw an exception if they are not met. With the `assertTrue`
and `assertFalse` fields you are able to specify the conditions under which an assertion will pass. Currently, the
toolbox supports the following assertions.

### currentopenshift

This assertion will allow you to test whether the target environment is _currently_ on a particular deploytarget.

For example, if we wanted to assert that a particular environment's deploytarget should be 3, we could use

```
  - name: Current openshift should be 3
    assertTrue: currentopenshift
    target: 3
```

If we wanted to assert that it _shouldn't_ currently be 3, we could do the following.

```
  - name: Current openshift should NOT be 3
    assertFalse: currentopenshift
    target: 3
```

Typically you would run this as part of the `prerequisites` step to ensure you aren't going to apply a set of
transformations in the wrong namespace.

### environmenttype

This allows you to assert that an environment is either "production" or "development"

```
- name: Current environment should be development, not production
  assertTrue: environmenttype
  environmentType: development
```

### fileexists

Will assert that a particular file exists on disk before running the migration

```
  - name: Test file exist
    assertTrue: fileexists
    filename: /tmp/.testfile
```

## Retrying on failure

All steps can take the following optional fields to set retry parameters

```
    retry: 3 #optional, default 0 - the number of times to retry step if failed
    retryDelaySeconds: 5 #optional, default 10 - the delay between retry attempts
```

## Step types

Currently, the toolbox supports the following functions.

### Exec

The exec step allows you to run a command from inside the current advanced task's container, or within another pod.

To run a command in the advanced task's container, you can do the following

```
steps:
  - name: run local echo
    type: exec
    local: true
    command: echo "This is running in the advanced task's container"
```

To run a command in another pod, you can run the following, specifying `deployment` with the deployment you're
targeting.

```
steps:
  - name: run echo on the cli pod
    type: exec
    deployment: cli
    command: echo "Running in the cli pod in namespace %namespace%"
```

### Text substitutions

The `exec` step allows you to do textual substitutions in the `command`.

It will do the following substitutions

* '%project%' for current project name
* '%environment%' for the current environment
* '%namespace%' for the current namespace

### scale

When running a command in another pod in the namespace, you will need to ensure that the pod is actually available. If
it has been auto-idled, for instance, you will need to scale up the deployment before running your command. The `scale`
step will attempt to scale up a deployment if there are currently no running pods.

```
steps:
  - name: Scale up cli deployment
    type: scale
    deployment: cli
```

### deploy

The deploy step will attempt to deploy the current environment.

```
  - name: Deploy
    type: deploy
    [skipDeploymentWait: true]
    [registerBuildIdAs: *variablename*]
    [passIfTextExistsInLogs: "STEP Cronjob Cleanup: Completed"]
```

The key/value pair `passIfTextExistsInLogs` is optional. Sometimes, despite a deployment failing, it may have deployed
far enough to be considered "deployed". This option allows you to specify some text that may appear in the logs that, if
there, let's us consider a deployment as "deployed", despite failing.

Sometimes you don't need to wait for the outcome of the deployment before moving on to another task, in this case you
can use `skipDeploymentWait: true`, this will simply move onto the next task after the deployment has begun.

If you would like to use the build id in subsequent steps (for example in a text substitution in an `exec` or as input
for a `waitfordeploy`) you can register a variable using `registerBuildIdAs`, which will then be available to subsequent
steps.

### waitfordeploy

```
steps:
  - name: Deploy
    type: deploy
    skipDeploymentWait: true
    registerBuildIdAs: thebuildid
  - name: echo build id
    type: exec
    local: true
    command: echo "%thebuildid%"
  - name: wait for deployment
    type: Waitfordeploy
    buildId: thebuildid
    [passIfTextExistsInLogs: "STEP Cronjob Cleanup: Completed"]
```

`waitfordeploy` will typically be used in combination with skipping a deploy and registering an id. 

### copyto

If there is a file that is delivered along with the advanced task, for instance, a particular version of the lagoon-sync
tool, this step type can be used to copy that file to the target deployment/pod.

This _requires_ the file to exist on the advanced task image.

```
  - name: copy lagoon sync to cli pod
    type: copyto
    deployment: cli
    source: /usr/bin/lagoon-sync
    destination: /tmp/lagoon-sync-from-advanced-task
```

In the example above, the file `/usr/bin/lagoon-sync` on the advanced task image, is copied to the cli pod
at `/tmp/lagoon-sync-from-advanced-task`

### setdeploytarget (dangerous)

This will change the deploy target for the current namespace. This is potentially an extremely destructive operation and
should be done with caution. Once the change is effected, the environment will need to be redeployed in order for the
shift to the new deploy target.  
In order to do this, you need the id of the new openshift/deploy target.

```
  - name: Change deploy target
    type: setdeploytarget
    target: <new deploy target id>
```

### setservicename (experimental/dangerous)

This allows you to set the service name for the currently running advanced task.

```
  - name: Set service name for current task
    type: setservicename
    servicename: advanced-task-service
```

This can be used if you need to `ssh` into the currently running advanced task.

### waitforfile

Will pause the task process until the given file exists on disk.

```
  - name: Wait for config file before continuing
    type: waitforfile
    filename: /etc/someservice/someconfigfile
```
