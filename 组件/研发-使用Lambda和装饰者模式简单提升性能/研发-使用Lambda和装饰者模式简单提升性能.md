## Lambda

在写代码的过程中，我们往往会将公用逻辑抽出来成为一个独立的方法，例如：

```java
void function(A a, B b) {
  biz
}
```

在某些参数或者线程上下文的情况下，biz不一定被执行。而如果参数的获取很耗费时间，这就是不必要的开销。可以使用Supplier来优化性能。

```java
void function(Supplier<A> aSupplier, Supplier<B> bSupplier) {
  // 某些条件下，biz不会被执行
  if (conditon) {
    return;
  }
	A a = aSupplier.get();
  B b = bSupplier.get();
  biz
}
```



## Lambda+装饰者

有些时候，一个参数可能被使用0次、1次或多次，例如：

```java
void function(Supplier<A> aSupplier, Supplier<B> bSupplier) {
  List list = queryFromDb();
  for (L l : list) {
    if (conditon(l)) {
      continue;
    }
    A a = aSupplier.get();
    B b = bSupplier.get();
    biz
  }
}
```

如果将Supplier#get()的执行提取到for外面，get()必须执行一次；如果放在for里面，可能执行多次。优化方案如下。

```java
void function(Supplier<A> aSupplier, Supplier<B> bSupplier) {
  List list = queryFromDb();
  A a = null;
  B b = null;
  for (L l : list) {
    if (!conditon(l)) {
      continue;
    }
    a = a == null ? aSupplier.get() : null;
    b = b == null ? bSupplier.get() : null;
    biz
  }
}
```

我们用装饰者模式装饰Supplier，可以取代三元表达式。

```java
public class ExecuteOnceSupplier<R> implements Supplier<R> {
  private final Supplier<R> supplier;
  private R r;

  public ExecuteOnceSupplier(Supplier<R> supplier) {
    this.supplier = supplier;
  }

  @Override
  public R get() {
    return r == null ? (r = supplier.get()) : r;
  }

  public static <R> ExecuteOnceSupplier<R> of(Supplier<R> supplier) {
    return new ExecuteOnceSupplier<>(supplier);
  }

}
```

使用装饰者模式优化后的实现如下：

```java
void function(Supplier<A> aSupplier, Supplier<B> bSupplier) {
  List list = queryFromDb();
  aSupplier = ExecuteOnceSupplier.of(aSupplier);
  bSupplier = ExecuteOnceSupplier.of(bSupplier);
  for (L l : list) {
    if (!conditon(l)) {
      continue;
    }
    A a = aSupplier.get();
    B b = bSupplier.get();
    biz
  }
}
```

同理，Function和Runnable也可以被包装为ExecuteOnceFunction和ExecuteOnceRunnable。

```java
public class ExecuteOnceFunction<T, R> implements Function<T, R> {

  private final Function<T, R> function;
  private R r;

  public ExecuteOnceFunction(Function<T, R> function) {
    this.function = function;
  }

  @Override
  public R apply(T t) {
    return r == null ? (r = function.apply(t)) : r;
  }

  public static <T, R> ExecuteOnceFunction<T, R> of(Function<T, R> function) {
    return new ExecuteOnceFunction<>(function);
  }

}
```

```java
public class ExecuteOnceRunnable implements Runnable {

  private final Runnable runnable;
  private boolean b = false;

  public ExecuteOnceRunnable(Runnable runnable) {
    this.runnable = runnable;
  }

  @Override
  public void run() {
    if (!b) {
      b = true;
      runnable.run();
    }
  }

  public static ExecuteOnceRunnable of(Runnable runnable) {
    return new ExecuteOnceRunnable(runnable);
  }

}
```









