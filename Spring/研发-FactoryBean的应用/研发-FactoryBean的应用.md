## 研发-FactoryBean的应用

开发中需要将我们的数据推送给另外一家公司（政府企业），交互的模式是MQ，两家公司之间的网络走的是专线。

对面公司提供了一个jar包，有类MqServiceFactory（后面简称Factory）：

```java
public class MqServiceFactory {

    public static MqServiceClient create() {
        System.out.println("创建了一个MQ Client对象！");
        return new MqServiceClient();
    }

    public static void close() {
        System.out.println("关闭了所有的MQ Client对象！");
    }

}
```

可以看出这个类提供了两个静态方法，一个是创建MqServiceClient，一个是关闭所有的MqServiceClient（后面简称Client）。

```java
public class MqServiceClient {

    public void send(String msg) {
        System.out.println("MqServiceClient: " + msg);
    }

}
```

Client里有一个send方法，我们只需要往里面写数据就行了。（不需要考虑类似于Kafka的Topic，Partition之类的问题）。

由于在多个业务过程中都需要发送数据，所以希望将Client作为单例注入到Spring容器中。同时在Spring容器关闭的时候需要调用Factory的close方法关闭连接。

### 装饰者模式

由于Client不能由Spring创建，所以首先想到的就是使用@Bean向Spring容器注入一个Client的实例。

```java
@Configuration
public class MqServiceConfiguration {
    
    @Bean
    public MqServiceClient mqServiceClient() {
       return new MqServiceFactory.create();
    }
    
}
```

但由于Client没有实现Bean的生命周期相关方法，所以我们没法在Spring容器关闭的时候销毁Client。所以我利用了装饰者模式装饰了一下Client，然后将装饰后的Client对象（后面简称ClientWrapper）注入Spring容器。

```java
public class MqServiceClientWrapper extends MqServiceClient implements DisposableBean {

    private MqServiceClient mqServiceClient;

    public MqServiceClientWrapper(MqServiceClient mqServiceClient) {
        this.mqServiceClient = mqServiceClient;
    }

    @Override
    public void send(String msg) {
        mqServiceClient.send(msg);
    }

    @Override
    public void destroy() throws Exception {
        MqServiceFactory.close();
    }

}
```

```java
@Configuration
public class MqServiceConfiguration {
    
    @Bean
    public MqServiceClient mqServiceClient() {
       return new MqServiceClientWrapper(MqServiceFactory.create());
    }
    
}
```

这样的话，在Spring容器关闭的时候就会调用ClientWrapper的destroy方法关闭Client。如此一来确实可以解决问题。

### FactoryBean

使用装饰者模式可以达到要求，但是有种很冗余的感觉，而且万一Client是final类，那就无法装饰了。

其实在Spring中，如果想注入一个无法由Spring创建的对象，利用FactoryBean是最好的方法，典型应用就是MyBatis的自动动态代理接口。使用FactoryBean的代码如下：

```java
public class MqServiceClientFactoryBean implements FactoryBean<MqServiceClient>, DisposableBean {

    @Override
    public MqServiceClient getObject() throws Exception {
        return MqServiceFactory.create();
    }

    @Override
    public Class<?> getObjectType() {
        return MqServiceClient.class;
    }

    @Override
    public void destroy() throws Exception {
        MqServiceFactory.close();
    }
}
```

```java
@Configuration
public class MqServiceConfiguration {

    @Bean
    public MqServiceClientFactoryBean mqServiceClient() {
        return new MqServiceClientFactoryBean();
    }

}
```

使用FactoryBean的场景：

> FactoryBean 通常是用来创建比较复杂的bean，一般的bean直接用xml配置即可，但如果一个bean的创建过程中涉及到很多其他的bean 和复杂的逻辑，用xml配置比较困难甚至是无法配置，这时可以考虑用FactoryBean。

### JVM关闭钩子

额外查了一下Spring是如何在JVM关闭的时候做到能销毁Bean的。

底层依赖就是JDK提供的shotdownhook：java.lang.Runtime#addShutdownHook。

```java
public class Test {
    public void start(){
        Runtime.getRuntime().addShutdownHook(new Thread(()-> 
                System.out.println("钩子函数被执行，可以在这里关闭资源")
        ));
    }
    public static void main(String[] args) throws Exception{
        new Test().start();
        System.out.println("主应用程序在执行");
    }
}
```

Spring的调用实现如下：org.springframework.context.support.AbstractApplicationContext#registerShutdownHook

```java
	public void registerShutdownHook() {
		if (this.shutdownHook == null) {
			// No shutdown hook registered yet.
			this.shutdownHook = new Thread(SHUTDOWN_HOOK_THREAD_NAME) {
				@Override
				public void run() {
					synchronized (startupShutdownMonitor) {
						doClose();
					}
				}
			};
			Runtime.getRuntime().addShutdownHook(this.shutdownHook);
		}
	}
```















