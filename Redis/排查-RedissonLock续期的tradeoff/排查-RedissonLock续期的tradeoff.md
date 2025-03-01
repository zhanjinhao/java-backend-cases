## 楔子

分布式锁是后端开发的常见技术。我司最开始的分布式锁是ZK，后来换成了Redisson（3.17.1）。更换锁实现之后并未出现问题，但是本周连续出现了两个问题。所以分析一下并记录。

 

## 问题

### 问题1

不使用看门狗，导致锁过期，产生并发问题。

有一个接口，用于批量处理数据，为了防止出现并发问题，就加了分布式锁。最开始，接口只需要3s就能完成，所以加了10s的分布式锁。随着数据量的增大，早期的加锁时间评估已经失效，目前接口需要12s才能处理完成。于是锁在Redis服务端已经没有了，进而在unlock的时候，解锁失败，接口报错。

### 问题2

使用看门狗，unlock失败，导致看门狗无限续期。

有一个推数功能是后台有定时任务在不断的拉数据库数据去计算并推数。为了防止并发问题，我们加了分布式锁并使用了看门狗。业务反馈数据延迟很久没有推出去，经过排查是锁一直没有释放，重启服务后又继续推数。后来排查日志，发现在unlock的时候发生了网络异常。由于Redisson底层大量使用异步编程，我们没能找到直接的代码关联，但Github的issue中也有无限续期的场景，所以可以判断是unlock失败后没有取消看门狗，进而锁无限续期。

- [Redis renewExpiration ttl problem · Issue #3714 · redisson/redisson](https://github.com/redisson/redisson/issues/3714)
- [when redisson unlock throw Exception cause The lock is not released · Issue #4232 · redisson/redisson](https://github.com/redisson/redisson/issues/4232)
- [When the business thread is interrupted, watchdog renew listener keeps renewing lock · Issue #4368 · redisson/redisson](https://github.com/redisson/redisson/issues/4368)

### 问题3（推理）

如果如果看门狗续期失败了，会怎么办？

```java
private void renewExpiration() {
  ExpirationEntry ee = EXPIRATION_RENEWAL_MAP.get(getEntryName());
  if (ee == null) {
    return;
  }

  Timeout task = commandExecutor.getConnectionManager().newTimeout(new TimerTask() {
    @Override
    public void run(Timeout timeout) throws Exception {
      ExpirationEntry ent = EXPIRATION_RENEWAL_MAP.get(getEntryName());
      if (ent == null) {
        return;
      }
      Long threadId = ent.getFirstThreadId();
      if (threadId == null) {
        return;
      }

      // 具体执行代码续期的地方
      CompletionStage<Boolean> future = renewExpirationAsync(threadId);
      future.whenComplete((res, e) -> {
        if (e != null) {
          log.error("Can't update lock " + getRawName() + " expiration", e);
          EXPIRATION_RENEWAL_MAP.remove(getEntryName());
          return;
        }

        if (res) {
          // reschedule itself
          renewExpiration();
        } else {
          cancelExpirationRenewal(null);
        }
      });
    }
  }, internalLockLeaseTime / 3, TimeUnit.MILLISECONDS);

  ee.setTimeout(task);
}
```

从上面看门狗续期的源代码中可以发现，如果看门狗续期失败，会打印异常日志并且取消定时任务，即此时其他线程可以拿到锁，进而出现并发问题。所以使用了看门狗也会出现并发问题。

### 总结

Redisson的锁。客户端这里总结得到三个问题：

1. 使用看门狗，unlock失败，锁会不释放。
2. 使用看门狗，如果续期失败，不会再重试，所以锁会被其他线程拿去。
3. 不使用看门狗，加锁超时会丢锁。

我觉得还是得用看门狗。前两个问题都通过加日志解决，即：续期成功和续期失败的时候都打日志，并在日志系统配置告警。第三个问题表面上是Redisson不自动续期，导致锁过期了，深层原因是代码执行耗时太长，所以需要优化代码实现。

总结一下技术方面的解决方案。

1. renewttl的时候加日志，提前通知研发，这些逻辑有性能问题，因为正常情况下，是不会执行renewttl逻辑的。
2. 封装RedissonLock，unlock异常打日志，并且监控日志。

除了技术意外，业务方面也可以加一些监控来提前发现问题：

1. 后台定时器场景：需要加业务监控。比如本地事务表，需要监控数据是否发送出去了。
2. 前端接口：用户会报障。



## renewttl加日志

Redisson在3.44.0的时候，重构了renewttl的实现。截至到写本篇文章的时间（2025-02-25），3.44.0版本并未在生产中大规模应用，所以我加日志的实现针对的是3.43.0及之前的版本。

RedissonLock里真正进行续期的代码为renewExpirationAsync()。RedissonLock执行的实现代码如下：

```java
protected CompletionStage<Boolean> renewExpirationAsync(long threadId) {
  return commandExecutor.syncedEval(getRawName(), LongCodec.INSTANCE, RedisCommands.EVAL_BOOLEAN,
                                    "if (redis.call('hexists', KEYS[1], ARGV[2]) == 1) then " +
                                    "redis.call('pexpire', KEYS[1], ARGV[1]); " +
                                    "return 1; " +
                                    "end; " +
                                    "return 0;",
                                    Collections.singletonList(getRawName()),
                                    internalLockLeaseTime, getLockName(threadId));
}
```

对renewExpirationAsync()加日志的思路为先动态代理RedissonClient得到自定义的RedissonLock，再在自定义的RedissonLock里重写renewExpirationAsync()。具体实现如下：

**包装RedissonClient**

```java
@Slf4j
public class RenewLogInvocationHandler2 implements InvocationHandler {

  private final RedissonClient redissonClient;

  /**
   * true： 只能调用包装后的方法，调用非包装的方法抛异常。
   * false：可以调用非包装的方法。调用非包装的方法时和调用原始的方法一样。
   */
  private final boolean forceInvokeWrap;

  public RenewLogInvocationHandler2(RedissonClient redissonClient, boolean forceInvokeWrap) {
    this.redissonClient = redissonClient;
    this.forceInvokeWrap = forceInvokeWrap;
    if (log.isInfoEnabled()) {
      log.info("A proxy of RedissonClient[{}] has been created. The field `forceInvokeWrap` is set to [{}]", redissonClient, forceInvokeWrap);
    }
  }

  @Override
  public Object invoke(Object proxy, Method method, Object[] args) throws Throwable {
    String methodName = method.getName();
    if ("getFairLock".equals(methodName) && args.length == 1 && args[0] != null
            && String.class.isAssignableFrom(args[0].getClass())) {
      CommandAsyncExecutor commandExecutor = ((Redisson) redissonClient).getCommandExecutor();
      String name = (String) args[0];
      log(RenewLogRedissonFairLock.class, RedissonFairLock.class, name);
      return new RenewLogRedissonFairLock(commandExecutor, (String) args[0]);
    }
    if ("getLock".equals(methodName) && args.length == 1 && args[0] != null
            && String.class.isAssignableFrom(args[0].getClass())) {
      String name = (String) args[0];
      CommandAsyncExecutor commandExecutor = ((Redisson) redissonClient).getCommandExecutor();
      log(RenewLogRedissonLock.class, RedissonLock.class, name);
      return new RenewLogRedissonLock(commandExecutor, name);
    }
    if ("getReadWriteLock".equals(methodName) && args.length == 1 && args[0] != null
            && String.class.isAssignableFrom(args[0].getClass())) {
      CommandAsyncExecutor commandExecutor = ((Redisson) redissonClient).getCommandExecutor();
      String name = (String) args[0];
      log(RenewLogRedissonReadWriteLock.class, RedissonReadWriteLock.class, name);
      return new RenewLogRedissonReadWriteLock(commandExecutor, name);
    }
    if (!forceInvokeWrap) {
      return method.invoke(redissonClient, args);
    }
    throw new UnsupportedOperationException("RenewLogRedissonClient only support the following methods: getLock、getFairLock、getReadWriteLock, since the `forceInvokeWrap` field is set to [true].");
  }

  private void log(Class<?> clazz, Class<?> originalClazz, String name) {
    if (log.isDebugEnabled()) {
      log.debug("Return an object of {}[{}] instead of {}, and the return will log error msg when renewing ttl.",
              clazz.getSimpleName(), name, originalClazz.getSimpleName());
    }
  }

}
```

**重写renewExpirationAsync()**

```java
@Slf4j
public class RenewLogRedissonLock extends RedissonLock {

  public RenewLogRedissonLock(CommandAsyncExecutor commandExecutor, String name) {
    super(commandExecutor, name);
  }

  public RenewLogRedissonLock(String name, CommandAsyncExecutor commandExecutor) {
    super(name, commandExecutor);
  }

  @Override
  protected CompletionStage<Boolean> renewExpirationAsync(long threadId) {
    CompletionStage<Boolean> stage = super.renewExpirationAsync(threadId);
    return stage
            .whenComplete(new BiConsumer<Boolean, Throwable>() {
              @Override
              public void accept(Boolean aBoolean, Throwable throwable) {
                if (throwable != null) {
                  log.error("[{}] failed to renew RedissonLock [{}], and a throwable is been thrown.", getLockName(threadId), getRawName(), throwable);
                  return;
                }
                if (Boolean.TRUE.equals(aBoolean)) {
                  log.error("[{}] succeed in renewing RedissonLock [{}].", getLockName(threadId), getRawName());
                  return;
                }
                if (Boolean.FALSE.equals(aBoolean)) {
                  log.error("[{}] failed to renew RedissonLock [{}].", getLockName(threadId), getRawName());
                  return;
                }
              }
            });
  }

}
```

**再提供一个动态代理工具类**

```java
public static RedissonClient wrap(RedissonClient redissonClient, boolean forceInvokeWrap) {
  InvocationHandler ds = new RenewLogInvocationHandler2(redissonClient, false);
  return (RedissonClient) Proxy.newProxyInstance(
    redissonClient.getClass().getClassLoader(), redissonClient.getClass().getInterfaces(), ds);
}
```



