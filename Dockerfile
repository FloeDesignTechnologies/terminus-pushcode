FROM composer/composer

# Create the account for the user to use when running the container
ARG USER_UID=1000
ARG USER=php
RUN useradd --uid ${USER_UID} --create-home ${USER} &&\
    # Make sure the running user own the $COMPOSER_HOME
    chown ${USER_UID} -R ${COMPOSER_HOME}

# Run container as the non-root user configured and created above
USER ${USER_UID}

RUN mkdir -p /home/${USER}/.terminus/plugins

WORKDIR /home/${USER}/.terminus/plugins/push-code

ENV PATH=$PATH:/home/${USER}/.terminus/plugins/push-code/vendor/bin