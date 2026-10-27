# Docker Tutorial
## Part 1 — Building and Running a PHP Application in a Container

### 1. Introduction

In this tutorial, we will build a simple PHP application and run it inside a Docker container.

Rather than installing PHP and Apache directly on our computer, we will use an existing Docker image that already contains everything we need to serve a PHP application.

By the end of this tutorial, you will understand how to:

- Build a Docker image using a `Dockerfile`.
- Run a container from that image.
- Use Docker Compose to manage the application.
- Access the application through your web browser.
- Inspect and manage the container using Docker commands.

We will use PHP 8.3 with Apache. In a later part of this tutorial, we will introduce a database and then configure persistent storage for it.

For now, our application will simply display a message in the browser.

### 2. Prerequisites

Before starting, ensure you have Docker installed and running.

You can check that Docker is available by opening a terminal and running:

```bash
docker --version
```

You should see output identifying the installed Docker version.

Next, check that Docker Compose is available:

```bash
docker compose version
```

Docker Compose allows us to define our application's configuration in a file rather than having to remember and execute every Docker command individually.

If either command fails, resolve the Docker installation before continuing.

You can find installation instructions in the official Docker documentation:

- Docker Engine: https://docs.docker.com/engine/install/
- Docker Desktop: https://docs.docker.com/desktop/
- Docker Compose: https://docs.docker.com/compose/

You do not need to install PHP or Apache directly on your computer. Docker will provide these through the image we use.

### 3. Create the project structure

Create a directory for the project.

We will call it `php-docker-tutorial`.

Inside that directory, create the following structure:

```text
php-docker-tutorial/
├── app/
│   ├── Dockerfile
│   └── index.php
└── compose.yml
```

Each file has a specific purpose.

| File | Purpose |
|---|---|
| `app/Dockerfile` | Defines how our Docker image is built. |
| `app/index.php` | Contains the PHP application we want to run. |
| `compose.yml` | Defines how Docker Compose builds and runs the application. |

The `app` directory contains the application and the instructions for building its image.

The `compose.yml` file sits at the project root because it describes how the application should be run.

We will create each file in turn.

### 4. Create the PHP application

Create a file named `index.php` inside the `app` directory.

Add the following code:

```php
<?php

echo '<h1>Hello from Docker!</h1>';
echo '<p>This PHP application is running inside a container.</p>';
```

This is our entire application.

When Apache receives a request for the page, it will execute the PHP code and return the resulting HTML to the browser.

We could build a much more complicated application, but keeping things simple allows us to concentrate on Docker rather than PHP development.

Save the file.

### 5. Create the Dockerfile

Next, create a file named `Dockerfile` inside the `app` directory.

Add the following instruction:

```dockerfile
FROM php:8.3-apache
```

That's the entire Dockerfile for this first exercise.

Let's understand what it means.

#### What is a Docker image?

A Docker image is a packaged filesystem and configuration used to create containers.

An image can contain an operating system's userspace, libraries, runtime environments, application files and other dependencies.

Rather than building an entire environment ourselves, we can use an image published by someone else as our starting point.

In this case, we are using the official PHP image maintained through the PHP Docker project.

The image `php:8.3-apache` provides:

- PHP 8.3.
- Apache HTTP Server.
- Configuration that allows Apache to execute PHP files.
- A default web document root at `/var/www/html`.

The image is available from Docker Hub.

#### What does `FROM` mean?

The `FROM` instruction specifies the base image from which our image will be built.

Our Dockerfile therefore says:

"Start with the PHP 8.3 image that includes Apache."

Docker will use that image as the foundation for our own image.

We could add further instructions to install extensions, copy application files, or change configuration. For this first exercise, we do not need to do any of those things.

We will provide our application files through a bind mount in the next section, so we do not need to copy them into the image at build time.

### 6. Configure Docker Compose

At the root of the project, create `compose.yml`.

Add the following configuration:

```yaml
services:
  app:
    build: ./app
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www/html
```

This file describes how Docker Compose should build and run our application.

Let's examine each section.

#### `services`

```yaml
services:
```

A service describes an application component that Docker Compose can build or run.

Our project currently has just one service, which we have named `app`.

In a later tutorial, we will add a second service for our database.

#### `build`

```yaml
build: ./app
```

This tells Docker Compose to build an image using the files in the `app` directory.

Docker looks for a file named `Dockerfile` in that directory and uses its instructions to build the image.

In our case, the build process starts from `php:8.3-apache`.

The resulting image will contain the PHP and Apache environment required by our application.

Notice that we have not specified an `image` name here. Docker Compose can build and manage the resulting image without us explicitly naming it.

#### `ports`

```yaml
ports:
  - "8080:80"
```

Apache listens on port 80 inside the container.

However, our computer cannot access that port through the host's network interface unless we expose it.

This configuration maps port 8080 on our computer to port 80 inside the container.

The format is:

```text
HOST_PORT:CONTAINER_PORT
```

Therefore:

- We access the application through port `8080` on our computer.
- Docker forwards the connection to port `80` inside the container.
- Apache receives the request and serves the application.

We will access the application at:

`http://localhost:8080`

#### `volumes`

```yaml
volumes:
  - ./app:/var/www/html
```

This configuration creates a bind mount.

A bind mount makes a directory on the host computer available at a specified location inside the container.

In this example:

- `./app` refers to the `app` directory in our project.
- `/var/www/html` is Apache's document root inside the container.

The result is that our local application files are available to Apache at the expected location.

This means that if we edit `index.php` on our computer, the changes are immediately visible inside the running container. We do not need to rebuild the image just to change the PHP source code.

There is an important distinction here: a bind mount is not the same thing as a Docker-managed volume. We will explore persistent Docker volumes when we introduce the database in a later part of the tutorial.

For now, remember that this mount gives the container access to our application files.

### 7. Build and start the application

We are now ready to run the application.

Open a terminal in the project root, `php-docker-tutorial`.

Run:

```bash
docker compose up --build
```

Let's break down the command.

- `docker compose` invokes Docker Compose.
- `up` creates and starts the containers required by the configuration.
- `--build` tells Compose to build the image before starting the application.

Docker will download the PHP image if it is not already available locally, then use it to build our application image.

It will subsequently create and start the application container.

The first run may take a little while because Docker needs to download the base image.

When the application starts, Compose displays its logs in the terminal. Leave this terminal running for now.

Open your web browser and navigate to:

`http://localhost:8080`

You should see:

**Hello from Docker!**

This confirms that Apache is serving our page and PHP is executing inside the container.

Congratulations: you have built and run a PHP application in Docker.

### 8. Understand what we have built

Let's review the relationship between the different components.

**The Dockerfile** describes how to build our image.

**The image** contains the PHP and Apache environment needed to run the application.

**The container** is the running instance created from that image.

**The bind mount** makes our local application files available inside the container.

**The port mapping** makes the application accessible through our computer's browser.

The overall process looks like this:

```text
                 Dockerfile
                     |
                     v
              Build Docker image
                     |
                     v
               Create container
                     |
                     v
                Run Apache
                     |
                     v
              Execute PHP code
                     |
                     v
              Return HTML page
                     |
                     v
               Web browser
```

The bind mount connects our local `app` directory to Apache's document root inside the container.

This gives us a convenient development workflow: the runtime environment is provided by Docker, while we can edit the application on our computer.

### 9. Inspect the running container

While the application is running, open a second terminal in the project directory.

Run:

```bash
docker compose ps
```

This displays the containers associated with the current Compose project.

You should see the `app` container listed as running.

Now inspect the logs:

```bash
docker compose logs app
```

These logs can help you diagnose startup problems and other issues reported by the container.

Next, open a shell inside the running container:

```bash
docker compose exec app bash
```

You are now running commands inside the container rather than directly on your computer.

Try:

```bash
php --version
```

You should see PHP 8.3.

Now run:

```bash
apache2 -v
```

This displays the Apache version.

These commands demonstrate an important point: PHP and Apache are available inside the container because they are provided by the image. You did not need to install them directly on your computer.

To leave the container's shell, run:

```bash
exit
```

You should now be back in your original terminal.

### 10. Make a change to the application

Let's verify that the bind mount works as expected.

Open `app/index.php` and change the heading to:

```php
echo '<h1>Hello from my first Docker application!</h1>';
```

Save the file and refresh the browser.

The updated heading should appear immediately.

We did not need to rebuild the image or recreate the container.

Why?

The `./app:/var/www/html` bind mount makes the local `app` directory available inside the container. Apache reads the updated PHP file from that directory when the page is requested.

This is particularly useful during development because it allows us to work with our source code normally while keeping the application's runtime environment isolated inside Docker.

### 11. Starting, stopping and removing containers

So far, we've started our application using:

```bash
docker compose up --build
```

When we press `Ctrl+C`, the foreground Compose process stops the application. We can also manage the application's lifecycle using Docker Compose commands.

#### Start the application in the background

Normally, `docker compose up` displays the application's logs in the terminal and keeps running in the foreground.

We can use the `-d` option to start the application in the background instead:

```bash
docker compose up -d
```

The `-d` option stands for *detached mode*.

Docker Compose starts the application and returns control to the terminal, allowing us to continue working.

We can check whether the container is running using:

```bash
docker compose ps
```

We can still access the application at `http://localhost:8080`.

#### Stop the application

To stop the application without removing its container, run:

```bash
docker compose stop
```

This stops the running containers associated with the project but retains them.

You can verify this by running:

```bash
docker compose ps -a
```

The `-a` option includes stopped containers in the output.

You should see the application container listed, but it should no longer be running.

If you visit `http://localhost:8080` now, the application will not be available.

#### Start a stopped container

Because we stopped the container rather than removing it, we can start it again without recreating it:

```bash
docker compose start
```

Check its status:

```bash
docker compose ps
```

Refresh the browser and confirm that the application is available again.

Notice that `start` and `up` serve slightly different purposes:

- `docker compose start` starts existing, stopped containers.
- `docker compose up` creates and starts containers as necessary to bring the application into the desired state.

For example, if you have removed the application's container, `docker compose start` cannot start it because there is no existing container to start. You would use `docker compose up` instead.

#### Remove the container

Once we no longer need the application container, we can remove it.

First, stop the application if it is running:

```bash
docker compose down
```

This stops and removes the containers created for the Compose project, along with the project's default network if one was created.

It does not normally remove the image.

We can confirm that the container has been removed by running:

```bash
docker compose ps -a
```

The application container should no longer appear in the list.

We can recreate it whenever we want by running:

```bash
docker compose up -d
```

Docker Compose will use the existing image if appropriate, creating a new container from it.

### 12. Removing the Docker image

So far, we've created and run a container using an image built from our Dockerfile.

Removing a container does not automatically remove its image. Images can be retained and reused to create new containers.

Let's examine how to remove an image when we no longer need it.

#### List the available images

Run:

```bash
docker image ls
```

This displays the images available locally.

You should see the PHP base image, `php:8.3-apache`, along with the image built for our application.

Docker Compose generates a project-specific name for our application image when we don't specify one explicitly. The exact name depends on the Compose project and configuration.

#### Remove the application image

First, ensure that the application's container has been removed:

```bash
docker compose down
```

Next, identify the application image:

```bash
docker image ls
```

Copy the image ID or repository name for the image built by our project.

Then remove that image using its ID:

```bash
docker image rm <IMAGE_ID>
```

Replace `<IMAGE_ID>` with the actual image ID shown on your computer. Do not include the angle brackets.

Alternatively, you can remove the image using its repository name and tag, if it has one.

Docker will refuse to remove an image if a container still depends on it. If that happens, check whether you have any remaining containers using the image.

**Important:** Do not remove the `php:8.3-apache` base image unless you specifically want to remove it as well. Our application image was built from that image, but the two images are separate entries in the local image store.

Removing the application image also does not delete the project files on your computer. Your `Dockerfile`, `index.php` and `compose.yml` remain in the project directory.

#### Build the image again

Now that the application image has been removed, run:

```bash
docker compose up --build -d
```

Docker Compose will rebuild the application image and create a new container.

The PHP base image may be reused if it is still available locally.

Once the application starts, visit `http://localhost:8080` again to confirm that everything is working.

This demonstrates the relationship between images and containers:

- An image provides the basis for creating a container.
- A container is a runnable instance created from an image.
- Removing a container does not necessarily remove its image.
- Removing an image does not remove your original application source files.
- If the image is removed, it can be rebuilt from the Dockerfile.

### 13. Troubleshooting

#### The browser cannot connect to `localhost:8080`

Check that the container is running:

```bash
docker compose ps
```

If it is not running, inspect the logs:

```bash
docker compose logs app
```

Also check whether another application is already using port 8080 on your computer.

If necessary, change the host port in `compose.yml`, for example:

```yaml
ports:
  - "8081:80"
```

You would then access the application at `http://localhost:8081`.

#### Apache returns a 403 Forbidden response

Check that `app/index.php` exists and that the bind mount points to the correct directory.

Remember that the mount replaces the visible contents of `/var/www/html` with the contents of your local `app` directory.

If that directory is empty or the file is in the wrong location, Apache may not find the expected application.

You can inspect the directory from inside the container:

```bash
docker compose exec app ls -la /var/www/html
```

You should see `index.php`.

#### Docker cannot find the Dockerfile

Check that your project has the expected structure:

```text
php-docker-tutorial/
├── app/
│   ├── Dockerfile
│   └── index.php
└── compose.yml
```

Make sure you run Docker Compose from the project root.

Also ensure that the file is named exactly `Dockerfile`, without an additional extension such as `.txt`.

#### Docker cannot download the base image

Check that Docker is running and that your machine has network access to the image registry.

You can also try downloading the image explicitly:

```bash
docker pull php:8.3-apache
```

If this fails, investigate the error message before proceeding.

### 14. Checkpoint

Before moving on, make sure you can answer the following questions.

1. What is the difference between a Docker image and a container?
2. What does the `FROM` instruction do in a Dockerfile?
3. Why do we use Docker Compose?
4. What does `"8080:80"` mean in our port configuration?
5. What does the bind mount `./app:/var/www/html` achieve?
6. Why can we edit `index.php` without rebuilding the image?
7. Which command would you use to inspect the application's logs?
8. Which command would you use to open a shell inside the running container?
9. What is the difference between `docker compose stop` and `docker compose down`?
10. What is the difference between `docker compose start` and `docker compose up`?
11. Does removing a container automatically remove its image?
12. How would you remove the application image and rebuild it?

Try answering these without looking back at the explanations.

### 15. Summary

In this exercise, we created a basic PHP application, built an image from the official PHP Apache image, and ran it inside a Docker container.

We used Docker Compose to describe the application configuration, mapped a host port to Apache's port, and mounted our local source directory into the container.

We also learned how to inspect, interact with, stop, start, remove and recreate containers, as well as how to remove and rebuild an image.

Our application is deliberately simple, but we have established the foundation for a more realistic system.

In **Part 2**, we will introduce a MySQL database and update the application to store and retrieve data. This will give us our first multi-container application and provide the context for understanding why database storage needs to be handled differently from application source code.

### Quick Reference Guide
| Task | Command |
|---|---|
| Start in the foreground | `docker compose up` |
| Start in the background | `docker compose up -d` |
| Stop the application | `docker compose stop` |
| Start existing stopped containers | `docker compose start` |
| Stop and remove containers | `docker compose down` |
| List running containers | `docker compose ps` |
| List all project containers, including stopped ones | `docker compose ps -a` |
| View application logs | `docker compose logs app` |
| List local images | `docker image ls` |
| Remove an image | `docker image rm <IMAGE_ID>` |
| Rebuild and start the application | `docker compose up --build -d` |